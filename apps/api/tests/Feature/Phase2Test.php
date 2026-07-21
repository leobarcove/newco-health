<?php

use App\Modules\Consults\Models\Consult;
use App\Modules\Doctors\Models\Doctor;
use App\Modules\Patients\Models\Organisation;
use App\Modules\Patients\Models\OrganisationMembership;
use App\Modules\Patients\Models\Patient;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payouts\Models\DoctorEarning;
use App\Modules\Programmes\Models\Programme;
use App\Modules\Programmes\Models\ProgrammeEnrolment;
use Database\Seeders\ProgrammeSeeder;
use Illuminate\Support\Facades\DB;

// ——— Payout batches ———

it('pays pending earnings weekly per doctor, skipping those without bank details', function () {
    $banked = Doctor::factory()->create(['paystack_recipient_code' => 'RCP_test123']);
    $unbanked = Doctor::factory()->create();

    foreach ([[$banked, 162500], [$banked, 162500], [$unbanked, 162500]] as [$doctor, $amount]) {
        DoctorEarning::create([
            'doctor_id' => $doctor->id,
            'consult_id' => Consult::factory()->create(['doctor_id' => $doctor->id, 'state' => Consult::STATE_CONCLUDED])->id,
            'amount_kobo' => $amount,
            'status' => DoctorEarning::STATUS_PENDING,
        ]);
    }

    $this->artisan('payouts:run')->assertSuccessful();

    $paid = DoctorEarning::where('doctor_id', $banked->id)->get();
    expect($paid->every(fn ($e) => $e->status === DoctorEarning::STATUS_PAID))->toBeTrue()
        ->and($paid->pluck('payout_reference')->unique())->toHaveCount(1) // one transfer for both
        ->and($paid->first()->payout_reference)->toStartWith('PO-')
        // No bank details → untouched, never lost.
        ->and(DoctorEarning::where('doctor_id', $unbanked->id)->first()->status)->toBe(DoctorEarning::STATUS_PENDING);

    // Second run: nothing left to pay for the banked doctor.
    $this->artisan('payouts:run')->assertSuccessful();
    expect(DoctorEarning::where('status', DoctorEarning::STATUS_PAID)->count())->toBe(2);
});

// ——— 72h follow-up close sweep ———

it('closes consults after the 72-hour follow-up window', function () {
    $stale = Consult::factory()->create(['state' => Consult::STATE_CONCLUDED, 'concluded_at' => now()->subHours(80)]);
    $fresh = Consult::factory()->create(['state' => Consult::STATE_CONCLUDED, 'concluded_at' => now()->subHours(10)]);

    $this->artisan('consults:close-followups')->assertSuccessful();

    expect($stale->refresh()->state)->toBe(Consult::STATE_CLOSED)
        ->and($fresh->refresh()->state)->toBe(Consult::STATE_CONCLUDED);
});

// ——— Organisations as payers ———

function orgWithMember(Patient $patient, int $balanceKobo): Organisation
{
    $organisation = Organisation::create(['name' => 'Acme Nigeria Ltd', 'balance_kobo' => $balanceKobo]);
    OrganisationMembership::create(['organisation_id' => $organisation->id, 'patient_id' => $patient->id]);

    return $organisation;
}

it('covers a member consult from the employer float, before any sponsor', function () {
    config(['pricing.payments_required' => true]);
    $patient = Patient::factory()->create();
    $organisation = orgWithMember($patient, 1_000_000);

    $this->actingAs($patient->user)
        ->postJson('/api/consults', ['complaint' => 'Recurring headaches at work'])
        ->assertCreated()
        ->assertJsonPath('state', Consult::STATE_QUEUED);

    expect($organisation->refresh()->balance_kobo)->toBe(750_000)
        ->and(Payment::where('gateway', 'organisation')->count())->toBe(1);
});

it('falls through to self-pay when the employer float cannot cover', function () {
    config(['pricing.payments_required' => true]);
    $patient = Patient::factory()->create();
    $organisation = orgWithMember($patient, 100_000); // below the fee

    $this->actingAs($patient->user)
        ->postJson('/api/consults', ['complaint' => 'Sore eyes'])
        ->assertCreated()
        ->assertJsonPath('state', Consult::STATE_TRIAGED); // awaiting self-payment

    expect($organisation->refresh()->balance_kobo)->toBe(100_000); // untouched
});

// ——— Chronic-care programmes ———

it('enrols a patient (self-pay via the fake gateway) and activates the period', function () {
    config(['pricing.payments_required' => true]);
    $this->seed(ProgrammeSeeder::class);
    $patient = Patient::factory()->create();
    $programme = Programme::where('name', 'Hypertension Care')->first();

    $this->actingAs($patient->user)
        ->postJson("/api/programmes/{$programme->id}/enrol")
        ->assertCreated()
        ->assertJsonPath('status', ProgrammeEnrolment::STATUS_ACTIVE);

    $enrolment = ProgrammeEnrolment::first();
    expect($enrolment->current_period_ends_at->isFuture())->toBeTrue()
        ->and($enrolment->next_check_in_at->diffInDays(now()))->toBeLessThanOrEqual(14)
        ->and(Payment::where('purpose', 'programme')->where('status', 'succeeded')->count())->toBe(1);

    // Double-enrolment rejected.
    $this->actingAs($patient->user)
        ->postJson("/api/programmes/{$programme->id}/enrol")
        ->assertStatus(422);

    // Catalogue reflects the enrolment.
    $catalogue = $this->actingAs($patient->user)->getJson('/api/programmes')->assertOk()->json();
    expect(collect($catalogue)->firstWhere('name', 'Hypertension Care')['enrolment']['status'])->toBe('active');
});

it('covers programme fees from the employer float when a member enrols', function () {
    config(['pricing.payments_required' => true]);
    $this->seed(ProgrammeSeeder::class);
    $patient = Patient::factory()->create();
    $organisation = orgWithMember($patient, 2_000_000);
    $programme = Programme::where('name', 'Diabetes Care')->first();

    $this->actingAs($patient->user)
        ->postJson("/api/programmes/{$programme->id}/enrol")
        ->assertCreated()
        ->assertJsonPath('status', ProgrammeEnrolment::STATUS_ACTIVE);

    expect($organisation->refresh()->balance_kobo)->toBe(750_000); // 2,000,000 − 1,250,000
});

it('nudges due check-ins and lapses expired periods on tick', function () {
    $this->seed(ProgrammeSeeder::class);
    $programme = Programme::first();

    $due = ProgrammeEnrolment::create([
        'programme_id' => $programme->id,
        'patient_id' => Patient::factory()->create()->id,
        'status' => ProgrammeEnrolment::STATUS_ACTIVE,
        'current_period_ends_at' => now()->addDays(10),
        'next_check_in_at' => now()->subHour(),
    ]);
    $expired = ProgrammeEnrolment::create([
        'programme_id' => $programme->id,
        'patient_id' => Patient::factory()->create()->id,
        'status' => ProgrammeEnrolment::STATUS_ACTIVE,
        'current_period_ends_at' => now()->subDay(),
        'next_check_in_at' => now()->addDays(5),
    ]);

    $this->artisan('programmes:tick')->assertSuccessful();

    expect($due->refresh())
        ->last_nudged_at->not->toBeNull()
        ->next_check_in_at->isFuture()->toBeTrue()
        ->and($expired->refresh()->status)->toBe(ProgrammeEnrolment::STATUS_LAPSED);
});

it('lets a patient cancel their enrolment but not someone else\'s', function () {
    $this->seed(ProgrammeSeeder::class);
    $patient = Patient::factory()->create();
    $stranger = Patient::factory()->create();

    $enrolment = ProgrammeEnrolment::create([
        'programme_id' => Programme::first()->id,
        'patient_id' => $patient->id,
        'status' => ProgrammeEnrolment::STATUS_ACTIVE,
        'current_period_ends_at' => now()->addMonth(),
        'next_check_in_at' => now()->addDays(14),
    ]);

    $this->actingAs($stranger->user)->postJson("/api/programme-enrolments/{$enrolment->id}/cancel")->assertForbidden();
    $this->actingAs($patient->user)->postJson("/api/programme-enrolments/{$enrolment->id}/cancel")->assertOk();
    expect($enrolment->refresh()->status)->toBe(ProgrammeEnrolment::STATUS_CANCELLED);
});

// ——— Hausa locale ———

it('serves system messages in hausa when the user chooses ha', function () {
    $patient = Patient::factory()->create();
    $this->actingAs($patient->user)->patchJson('/api/me', ['locale' => 'ha'])->assertOk();

    $consultId = $this->actingAs($patient->user)
        ->postJson('/api/consults', ['complaint' => 'Zazzabi da ciwon kai'])
        ->json('id');

    $messages = $this->actingAs($patient->user)->getJson("/api/consults/{$consultId}/messages")->json();
    expect(collect($messages)->pluck('body')->join(' '))->toContain('Kuna cikin layi');
});
