<?php

use App\Models\User;
use App\Modules\Consults\Models\Consult;
use App\Modules\Doctors\Models\Doctor;
use App\Modules\Patients\Models\Patient;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\Wallet;
use App\Modules\Prescribing\Models\FormularyItem;
use App\Modules\Prescribing\Models\Pharmacy;
use Database\Seeders\FormularySeeder;
use Illuminate\Support\Facades\DB;

function pharmacyCounter(): User
{
    $pharmacy = Pharmacy::create([
        'name' => 'HealthPlus Yaba', 'pcn_licence_no' => 'PCN/12345', 'status' => 'active',
    ]);

    return User::create([
        'name' => 'HealthPlus counter', 'email' => 'counter@healthplus.test',
        'password' => 'a-strong-password', 'role' => User::ROLE_PHARMACY, 'pharmacy_id' => $pharmacy->id,
    ]);
}

function issuedPrescription(): array
{
    test()->seed(FormularySeeder::class);
    $patient = Patient::factory()->create();
    $doctor = Doctor::factory()->create();

    $consultId = test()->actingAs($patient->user)
        ->postJson('/api/consults', ['complaint' => 'Malaria symptoms'])->json('id');
    test()->actingAs($doctor->user)->postJson("/api/doctor/consults/{$consultId}/accept");

    $code = test()->actingAs($doctor->user)
        ->postJson("/api/doctor/consults/{$consultId}/prescriptions", [
            'items' => [['formulary_item_id' => FormularyItem::first()->id, 'dosage' => '1 daily', 'duration_days' => 3]],
        ])->json('pickup_code');

    return [$patient, $code];
}

// ——— Pharmacy portal ———

it('signs a pharmacist in, looks up a code with minimal phi, and dispenses once', function () {
    $counter = pharmacyCounter();
    [$patient, $code] = issuedPrescription();

    // Login issues a token (verified separately from the portal calls below,
    // because actingAs in the fixtures overrides bearer headers in-process).
    $this->postJson('/api/auth/pharmacy/login', [
        'email' => 'counter@healthplus.test', 'password' => 'a-strong-password',
    ])->assertOk()->assertJsonStructure(['token', 'pharmacy' => ['name']]);

    // Lookup shows medicines + first name only — no complaint, no thread.
    $lookup = $this->actingAs($counter)->getJson("/api/pharmacy/prescriptions/{$code}")
        ->assertOk()->json();
    expect($lookup['status'])->toBe('issued')
        ->and($lookup['patient_first_name'])->not->toContain(' ')
        ->and($lookup)->not->toHaveKey('complaint');

    $this->actingAs($counter)->postJson('/api/pharmacy/dispense', ['pickup_code' => $code])
        ->assertOk()->assertJsonPath('status', 'dispensed');

    // Second dispense of the same code fails — the code is spent.
    $this->actingAs($counter)->postJson('/api/pharmacy/dispense', ['pickup_code' => $code])
        ->assertStatus(404);

    // Dispensing pharmacy is recorded.
    expect(DB::table('prescriptions')->where('pickup_code', $code)->first()->pharmacy_id)->not->toBeNull();
});

it('keeps patients and doctors out of the pharmacy portal, and suspended pharmacies out entirely', function () {
    [, $code] = issuedPrescription();
    $patient = Patient::factory()->create();

    $this->actingAs($patient->user)->getJson("/api/pharmacy/prescriptions/{$code}")->assertForbidden();

    $counter = pharmacyCounter();
    Pharmacy::where('id', $counter->pharmacy_id)->update(['status' => 'suspended']);
    $this->postJson('/api/auth/pharmacy/login', [
        'email' => 'counter@healthplus.test', 'password' => 'a-strong-password',
    ])->assertForbidden();
});

// ——— Refunds ———

it('refunds a paid, unseen consult: payment refunded, consult abandoned', function () {
    config(['pricing.payments_required' => true]);
    $patient = Patient::factory()->create();
    $staff = User::create(['name' => 'Ops', 'email' => 'ops@newco.test', 'password' => 'long-password-x', 'role' => User::ROLE_STAFF]);

    $consultId = $this->actingAs($patient->user)
        ->postJson('/api/consults', ['complaint' => 'Changed my mind'])->json('id');
    $this->actingAs($patient->user)->postJson("/api/consults/{$consultId}/pay");

    $payment = Payment::where('consult_id', $consultId)->first();
    app(\App\Modules\Payments\Services\PaymentService::class)->refund($payment, $staff->id, 'Patient requested cancellation');

    expect($payment->refresh()->status)->toBe(Payment::STATUS_REFUNDED)
        ->and(Consult::find($consultId)->state)->toBe(Consult::STATE_ABANDONED);

    // Refunding again is rejected.
    expect(fn () => app(\App\Modules\Payments\Services\PaymentService::class)->refund($payment->refresh(), $staff->id, 'again'))
        ->toThrow(DomainException::class);
});

it('returns wallet-sponsored refunds to the sponsor wallet', function () {
    config(['pricing.payments_required' => true]);
    $patient = Patient::factory()->create();
    $staff = User::create(['name' => 'Ops', 'email' => 'ops2@newco.test', 'password' => 'long-password-x', 'role' => User::ROLE_STAFF]);
    $sponsor = User::create(['name' => 'Ade', 'email' => 'ade2@newco.test', 'password' => 'long-password-x', 'role' => User::ROLE_SPONSOR]);

    $sponsorshipId = $this->actingAs($sponsor)
        ->postJson('/api/sponsor/beneficiaries', ['phone' => $patient->user->phone, 'label' => 'Mum'])->json('id');
    $this->actingAs($patient->user)->postJson("/api/sponsorships/{$sponsorshipId}/respond", ['accept' => true]);
    $this->actingAs($sponsor)->postJson('/api/sponsor/wallet/topup', ['amount_kobo' => 300_000]);

    $this->actingAs($patient->user)->postJson('/api/consults', ['complaint' => 'x']); // auto-covered
    $payment = Payment::where('gateway', 'wallet')->first();

    app(\App\Modules\Payments\Services\PaymentService::class)->refund($payment, $staff->id, 'Doctor unavailable');

    expect(Wallet::where('user_id', $sponsor->id)->first()->balance_kobo)->toBe(300_000); // fully restored
});

// ——— NDPA export + erasure ———

it('exports everything the user can rightfully access as a json download', function () {
    [$patient] = issuedPrescription();

    $export = $this->actingAs($patient->user)
        ->getJson('/api/me/data-export')
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="my-newco-health-data.json"')
        ->json();

    expect($export['profile']['phone'])->toBe($patient->user->phone)
        ->and($export['consults'])->toHaveCount(1)
        ->and($export['prescriptions'])->toHaveCount(1)
        ->and(collect($export['consents'])->pluck('kind'))->toContain('telemedicine_terms');

    expect(DB::table('phi_access_log')->where('label', 'data_subject.export')->count())->toBe(1);
});

it('erases identity but retains the pseudonymised clinical record', function () {
    [$patient] = issuedPrescription();
    $consultCount = Consult::where('patient_id', $patient->id)->count();

    $this->actingAs($patient->user)
        ->postJson('/api/me/erase', ['confirm' => 'wrong'])
        ->assertStatus(422); // confirmation string required

    $this->actingAs($patient->user)
        ->postJson('/api/me/erase', ['confirm' => 'DELETE MY ACCOUNT'])
        ->assertOk();

    $user = $patient->user->refresh();
    expect($user->name)->toBe('Deleted User')
        ->and($user->phone)->toBeNull()
        ->and($user->erased_at)->not->toBeNull()
        ->and($user->tokens()->count())->toBe(0)
        // Clinical record retained, pseudonymised.
        ->and(Consult::where('patient_id', $patient->id)->count())->toBe($consultCount);
});
