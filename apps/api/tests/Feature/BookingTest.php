<?php

use App\Modules\Doctors\Models\Doctor;
use App\Modules\Patients\Models\Patient;
use App\Modules\Scheduling\Models\AvailabilityException;
use App\Modules\Scheduling\Models\AvailabilityTemplate;
use App\Modules\Scheduling\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/** A doctor working Mondays 09:00–11:00 Lagos time, 20-minute slots. */
function mondayDoctor(): Doctor
{
    $doctor = Doctor::factory()->create();
    AvailabilityTemplate::create([
        'doctor_id' => $doctor->id,
        'weekday' => 1,
        'start_time' => '09:00',
        'end_time' => '11:00',
        'slot_minutes' => 20,
        'active' => true,
    ]);

    return $doctor;
}

/** Next Monday at a Lagos-local time, as a UTC ISO instant. */
function nextMondayAt(string $time): CarbonImmutable
{
    return CarbonImmutable::parse('next monday '.$time, 'Africa/Lagos')->utc();
}

beforeEach(function () {
    // Freeze time mid-week so "next monday" is always ≥3 days out (beyond lead time).
    Carbon::setTestNow(Carbon::parse('2026-07-22 12:00:00', 'Africa/Lagos')); // a Wednesday
});

it('generates slots from the weekly template, local-time correct', function () {
    $doctor = mondayDoctor();
    $patient = Patient::factory()->create();
    $monday = CarbonImmutable::parse('next monday', 'Africa/Lagos')->format('Y-m-d');

    $slots = $this->actingAs($patient->user)
        ->getJson("/api/booking/doctors/{$doctor->id}/slots?date={$monday}")
        ->assertOk()
        ->json();

    // 09:00–11:00 at 20 min = 6 slots; Lagos is UTC+1 so 09:00 local = 08:00Z.
    expect($slots)->toHaveCount(6)
        ->and($slots[0]['starts_at'])->toBe(nextMondayAt('09:00')->toIso8601String())
        ->and($slots[5]['starts_at'])->toBe(nextMondayAt('10:40')->toIso8601String());
});

it('books a slot and removes it from availability', function () {
    $doctor = mondayDoctor();
    $patient = Patient::factory()->create();
    $monday = CarbonImmutable::parse('next monday', 'Africa/Lagos')->format('Y-m-d');

    $this->actingAs($patient->user)
        ->postJson('/api/bookings', [
            'doctor_id' => $doctor->id,
            'starts_at' => nextMondayAt('09:20')->toIso8601String(),
            'complaint' => 'Recurring migraines',
        ])
        ->assertCreated()
        ->assertJsonPath('state', 'confirmed');

    $remaining = $this->actingAs($patient->user)
        ->getJson("/api/booking/doctors/{$doctor->id}/slots?date={$monday}")
        ->json();

    expect($remaining)->toHaveCount(5)
        ->and(collect($remaining)->pluck('starts_at'))->not->toContain(nextMondayAt('09:20')->toIso8601String());
});

it('refuses to double-book the same slot', function () {
    $doctor = mondayDoctor();
    $first = Patient::factory()->create();
    $second = Patient::factory()->create();
    $slot = nextMondayAt('09:00')->toIso8601String();

    $this->actingAs($first->user)
        ->postJson('/api/bookings', ['doctor_id' => $doctor->id, 'starts_at' => $slot])
        ->assertCreated();

    $this->actingAs($second->user)
        ->postJson('/api/bookings', ['doctor_id' => $doctor->id, 'starts_at' => $slot])
        ->assertStatus(500); // DomainException: slot no longer available

    expect(Booking::where('doctor_id', $doctor->id)->where('state', 'confirmed')->count())->toBe(1);
});

it('rejects times that were never offered slots', function () {
    $doctor = mondayDoctor();
    $patient = Patient::factory()->create();

    $this->actingAs($patient->user)
        ->postJson('/api/bookings', [
            'doctor_id' => $doctor->id,
            'starts_at' => nextMondayAt('09:10')->toIso8601String(), // off-grid time
        ])
        ->assertStatus(500);

    expect(Booking::count())->toBe(0);
});

it('honours whole-day unavailability exceptions', function () {
    $doctor = mondayDoctor();
    $patient = Patient::factory()->create();
    $monday = CarbonImmutable::parse('next monday', 'Africa/Lagos');

    AvailabilityException::create([
        'doctor_id' => $doctor->id,
        'date' => $monday->format('Y-m-d'),
        'kind' => AvailabilityException::KIND_UNAVAILABLE,
        'reason' => 'Annual leave',
    ]);

    $slots = $this->actingAs($patient->user)
        ->getJson("/api/booking/doctors/{$doctor->id}/slots?date={$monday->format('Y-m-d')}")
        ->json();

    expect($slots)->toBeEmpty();
});

it('honours partial blocks and ad-hoc extra hours', function () {
    $doctor = mondayDoctor();
    $patient = Patient::factory()->create();
    $monday = CarbonImmutable::parse('next monday', 'Africa/Lagos');

    // Block 09:00–10:00, add extra 14:00–15:00.
    AvailabilityException::create([
        'doctor_id' => $doctor->id, 'date' => $monday->format('Y-m-d'),
        'kind' => AvailabilityException::KIND_UNAVAILABLE,
        'start_time' => '09:00', 'end_time' => '10:00',
    ]);
    AvailabilityException::create([
        'doctor_id' => $doctor->id, 'date' => $monday->format('Y-m-d'),
        'kind' => AvailabilityException::KIND_EXTRA,
        'start_time' => '14:00', 'end_time' => '15:00', 'slot_minutes' => 20,
    ]);

    $slots = collect($this->actingAs($patient->user)
        ->getJson("/api/booking/doctors/{$doctor->id}/slots?date={$monday->format('Y-m-d')}")
        ->json())->pluck('starts_at');

    // 3 template slots survive (10:00–11:00) + 3 extra (14:00–15:00).
    expect($slots)->toHaveCount(6)
        ->and($slots)->not->toContain(nextMondayAt('09:00')->toIso8601String())
        ->and($slots)->toContain(nextMondayAt('14:00')->toIso8601String());
});

it('lets a patient cancel outside the cutoff but not inside it', function () {
    $patient = Patient::factory()->create();

    $far = Booking::factory()->create([
        'patient_id' => $patient->id,
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addMinutes(20),
    ]);
    $near = Booking::factory()->create([
        'patient_id' => $patient->id,
        'starts_at' => now()->addMinutes(30), // inside the 120-min cutoff
        'ends_at' => now()->addMinutes(50),
    ]);

    $this->actingAs($patient->user)->postJson("/api/bookings/{$far->id}/cancel")
        ->assertOk()->assertJsonPath('state', 'cancelled');

    $this->actingAs($patient->user)->postJson("/api/bookings/{$near->id}/cancel")
        ->assertStatus(500);
    expect($near->refresh()->state)->toBe(Booking::STATE_CONFIRMED);
});

it('reschedules by cancelling and rebooking atomically, linked', function () {
    $doctor = mondayDoctor();
    $patient = Patient::factory()->create();

    $originalId = $this->actingAs($patient->user)
        ->postJson('/api/bookings', [
            'doctor_id' => $doctor->id,
            'starts_at' => nextMondayAt('09:00')->toIso8601String(),
        ])->json('id');

    $replacement = $this->actingAs($patient->user)
        ->postJson("/api/bookings/{$originalId}/reschedule", [
            'starts_at' => nextMondayAt('10:00')->toIso8601String(),
        ])
        ->assertCreated()
        ->json();

    expect(Booking::find($originalId)->state)->toBe(Booking::STATE_CANCELLED)
        ->and(Booking::find($replacement['id'])->rescheduled_from_id)->toBe($originalId)
        ->and($replacement['starts_at'])->toBe(nextMondayAt('10:00')->toIso8601String());

    // The 09:00 slot is free again for someone else.
    $other = Patient::factory()->create();
    $this->actingAs($other->user)
        ->postJson('/api/bookings', [
            'doctor_id' => $doctor->id,
            'starts_at' => nextMondayAt('09:00')->toIso8601String(),
        ])->assertCreated();
});

it('blocks strangers from cancelling another patient\'s booking', function () {
    $booking = Booking::factory()->create(['starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addMinutes(20)]);
    $stranger = Patient::factory()->create();

    $this->actingAs($stranger->user)
        ->postJson("/api/bookings/{$booking->id}/cancel")
        ->assertForbidden();
});

it('lets the booked doctor begin the consult inside the window, assigned directly', function () {
    $booking = Booking::factory()->create([
        'starts_at' => now()->addMinutes(2),
        'ends_at' => now()->addMinutes(22),
        'complaint' => 'Follow-up on blood pressure readings',
    ]);
    $doctor = $booking->doctor;

    $consultId = $this->actingAs($doctor->user)
        ->postJson("/api/doctor/bookings/{$booking->id}/begin")
        ->assertCreated()
        ->assertJsonPath('state', 'in_consult')
        ->json('consult_id');

    expect($booking->refresh())
        ->state->toBe(Booking::STATE_COMPLETED)
        ->consult_id->toBe($consultId);

    // Both parties can use the thread immediately.
    $this->actingAs($booking->patient->user)
        ->postJson("/api/consults/{$consultId}/messages", ['body' => 'Good morning doctor'])
        ->assertCreated();
});

it('refuses to begin a booked consult far outside its window', function () {
    $booking = Booking::factory()->create([
        'starts_at' => now()->addHours(5),
        'ends_at' => now()->addHours(5)->addMinutes(20),
    ]);

    $this->actingAs($booking->doctor->user)
        ->postJson("/api/doctor/bookings/{$booking->id}/begin")
        ->assertStatus(500);

    expect($booking->refresh()->state)->toBe(Booking::STATE_CONFIRMED);
});

it('refuses begin by a different doctor than the one booked', function () {
    $booking = Booking::factory()->create([
        'starts_at' => now()->addMinutes(2),
        'ends_at' => now()->addMinutes(22),
    ]);
    $otherDoctor = Doctor::factory()->create();

    $this->actingAs($otherDoctor->user)
        ->postJson("/api/doctor/bookings/{$booking->id}/begin")
        ->assertStatus(500);
});

it('replaces the weekly availability template wholesale', function () {
    $doctor = Doctor::factory()->create();

    $this->actingAs($doctor->user)
        ->putJson('/api/doctor/availability', [
            'templates' => [
                ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00', 'slot_minutes' => 20],
                ['weekday' => 3, 'start_time' => '14:00', 'end_time' => '17:00', 'slot_minutes' => 30],
            ],
        ])
        ->assertOk()
        ->assertJsonCount(2);

    $this->actingAs($doctor->user)
        ->putJson('/api/doctor/availability', ['templates' => []])
        ->assertOk()
        ->assertJsonCount(0);
});

it('sends 24h and 1h reminders exactly once and sweeps no-shows', function () {
    Log::spy();

    $soon = Booking::factory()->create([
        'starts_at' => now()->addMinutes(30),
        'ends_at' => now()->addMinutes(50),
    ]);
    Booking::factory()->create([
        'starts_at' => now()->subHours(2),
        'ends_at' => now()->subHours(2)->addMinutes(20),
    ]); // missed → no-show

    $this->artisan('booking:send-reminders')->assertSuccessful();

    expect($soon->refresh())
        ->reminded_24h_at->not->toBeNull()
        ->reminded_1h_at->not->toBeNull();

    // Second run: nothing new to send.
    $this->artisan('booking:send-reminders')->assertSuccessful();

    expect(Booking::where('state', Booking::STATE_NO_SHOW)->count())->toBe(1);
});

it('lists bookable doctors with their next slot', function () {
    $doctor = mondayDoctor();
    Doctor::factory()->expiredLicence()->create(); // must not appear
    $patient = Patient::factory()->create();

    $doctors = $this->actingAs($patient->user)
        ->getJson('/api/booking/doctors')
        ->assertOk()
        ->json();

    expect($doctors)->toHaveCount(1)
        ->and($doctors[0]['id'])->toBe($doctor->id)
        ->and($doctors[0]['next_slot'])->toBe(nextMondayAt('09:00')->toIso8601String());
});
