<?php

use App\Modules\Doctors\Models\Doctor;
use App\Modules\Patients\Models\Patient;
use App\Modules\Scheduling\Models\AvailabilityTemplate;
use App\Modules\Scheduling\Models\Booking;
use App\Modules\Payments\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function liveConsultPair(): array
{
    $patient = Patient::factory()->create();
    $doctor = Doctor::factory()->create();

    $consultId = test()->actingAs($patient->user)
        ->postJson('/api/consults', ['complaint' => 'Chest tightness after exercise'])
        ->json('id');
    test()->actingAs($doctor->user)->postJson("/api/doctor/consults/{$consultId}/accept");

    return [$patient, $doctor, $consultId];
}

// ——— SOAP notes ———

it('lets the consulting doctor write and update a soap note', function () {
    [, $doctor, $consultId] = liveConsultPair();

    $this->actingAs($doctor->user)
        ->putJson("/api/doctor/consults/{$consultId}/notes", [
            'subjective' => 'Chest tightness on exertion, no rest pain',
            'assessment' => 'Likely musculoskeletal; rule out cardiac',
        ])
        ->assertOk()
        ->assertJsonPath('subjective', 'Chest tightness on exertion, no rest pain');

    // Update merges — one note per consult.
    $this->actingAs($doctor->user)
        ->putJson("/api/doctor/consults/{$consultId}/notes", ['plan' => 'ECG referral; review in 72h'])
        ->assertOk()
        ->assertJsonPath('plan', 'ECG referral; review in 72h');

    expect(DB::table('consult_notes')->where('consult_id', $consultId)->count())->toBe(1);
});

it('hides clinical notes from patients and other doctors', function () {
    [$patient, , $consultId] = liveConsultPair();
    $otherDoctor = Doctor::factory()->create();

    $this->actingAs($patient->user)->getJson("/api/doctor/consults/{$consultId}/notes")->assertForbidden();
    $this->actingAs($otherDoctor->user)->getJson("/api/doctor/consults/{$consultId}/notes")->assertForbidden();
    $this->actingAs($otherDoctor->user)
        ->putJson("/api/doctor/consults/{$consultId}/notes", ['plan' => 'x'])
        ->assertForbidden();
});

// ——— Attachments ———

it('uploads an image into the thread and streams it to participants only', function () {
    Storage::fake('local');
    [$patient, $doctor, $consultId] = liveConsultPair();
    $stranger = Patient::factory()->create();

    $messageId = $this->actingAs($patient->user)
        ->post("/api/consults/{$consultId}/attachments", [
            'kind' => 'image',
            'file' => UploadedFile::fake()->image('rash.jpg', 800, 600),
        ])
        ->assertCreated()
        ->json('id');

    // The thread lists it with a file_url and an empty body (no path leakage).
    $messages = $this->actingAs($doctor->user)->getJson("/api/consults/{$consultId}/messages")->json();
    $img = collect($messages)->firstWhere('id', $messageId);
    expect($img['kind'])->toBe('image')
        ->and($img['body'])->toBe('')
        ->and($img['file_url'])->toContain("/messages/{$messageId}/file");

    // Participants stream it; strangers are blocked; access is PHI-logged.
    $this->actingAs($doctor->user)->get("/api/consults/{$consultId}/messages/{$messageId}/file")->assertOk();
    $this->actingAs($stranger->user)->get("/api/consults/{$consultId}/messages/{$messageId}/file")->assertForbidden();
    expect(DB::table('phi_access_log')->where('label', 'attachment.read')->count())->toBe(1);
});

it('rejects disallowed file types and non-participants uploading', function () {
    Storage::fake('local');
    [$patient, , $consultId] = liveConsultPair();
    $stranger = Patient::factory()->create();

    $this->actingAs($patient->user)
        ->post("/api/consults/{$consultId}/attachments", [
            'kind' => 'image',
            'file' => UploadedFile::fake()->create('script.svg', 10, 'image/svg+xml'),
        ])
        ->assertStatus(422); // SVG rejected — script-capable format, never accepted as an image

    $this->actingAs($stranger->user)
        ->post("/api/consults/{$consultId}/attachments", [
            'kind' => 'image',
            'file' => UploadedFile::fake()->image('x.jpg'),
        ])
        ->assertForbidden();
});

// ——— Booking payments ———

function bookableSlot(): array
{
    Carbon::setTestNow(Carbon::parse('2026-07-22 12:00:00', 'Africa/Lagos'));
    $doctor = Doctor::factory()->create();
    AvailabilityTemplate::create([
        'doctor_id' => $doctor->id, 'weekday' => 1,
        'start_time' => '09:00', 'end_time' => '11:00', 'slot_minutes' => 20, 'active' => true,
    ]);

    return [$doctor, CarbonImmutable::parse('next monday 09:00', 'Africa/Lagos')->utc()->toIso8601String()];
}

it('holds a booked slot pending payment, then confirms on payment', function () {
    config(['pricing.payments_required' => true]);
    [$doctor, $slot] = bookableSlot();
    $patient = Patient::factory()->create();
    $rival = Patient::factory()->create();

    $bookingId = $this->actingAs($patient->user)
        ->postJson('/api/bookings', ['doctor_id' => $doctor->id, 'starts_at' => $slot])
        ->assertCreated()
        ->assertJsonPath('state', Booking::STATE_PENDING_PAYMENT)
        ->json('id');

    // The unpaid hold still blocks the slot for everyone else.
    $this->actingAs($rival->user)
        ->postJson('/api/bookings', ['doctor_id' => $doctor->id, 'starts_at' => $slot])
        ->assertStatus(422);

    $this->actingAs($patient->user)
        ->postJson("/api/bookings/{$bookingId}/pay")
        ->assertCreated()
        ->assertJsonPath('booking_state', Booking::STATE_CONFIRMED);
});

it('auto-covers a booking from the sponsor wallet', function () {
    config(['pricing.payments_required' => true]);
    [$doctor, $slot] = bookableSlot();
    $patient = Patient::factory()->create();

    $sponsor = \App\Models\User::create([
        'name' => 'Ade', 'email' => 'ade@example.com', 'password' => 'long-enough-pass', 'role' => 'sponsor',
    ]);
    $sponsorshipId = $this->actingAs($sponsor)
        ->postJson('/api/sponsor/beneficiaries', ['phone' => $patient->user->phone, 'label' => 'Mum'])
        ->json('id');
    $this->actingAs($patient->user)->postJson("/api/sponsorships/{$sponsorshipId}/respond", ['accept' => true]);
    $this->actingAs($sponsor)->postJson('/api/sponsor/wallet/topup', ['amount_kobo' => 500_000]);

    $this->actingAs($patient->user)
        ->postJson('/api/bookings', ['doctor_id' => $doctor->id, 'starts_at' => $slot])
        ->assertCreated()
        ->assertJsonPath('state', Booking::STATE_CONFIRMED); // paid silently

    expect(Wallet::where('user_id', $sponsor->id)->first()->balance_kobo)->toBe(250_000);
});

it('expires unpaid holds after 15 minutes and frees the slot', function () {
    config(['pricing.payments_required' => true]);
    [$doctor, $slot] = bookableSlot();
    $patient = Patient::factory()->create();
    $rival = Patient::factory()->create();

    $bookingId = $this->actingAs($patient->user)
        ->postJson('/api/bookings', ['doctor_id' => $doctor->id, 'starts_at' => $slot])
        ->json('id');

    Carbon::setTestNow(now()->addMinutes(16));
    $this->artisan('booking:send-reminders')->assertSuccessful();

    expect(Booking::find($bookingId)->state)->toBe(Booking::STATE_CANCELLED);

    // The slot is bookable again.
    $this->actingAs($rival->user)
        ->postJson('/api/bookings', ['doctor_id' => $doctor->id, 'starts_at' => $slot])
        ->assertCreated();
});
