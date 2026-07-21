<?php

use App\Modules\Compliance\Services\FeatureFlags;
use App\Modules\Consults\Models\Consult;
use App\Modules\Doctors\Models\Doctor;
use App\Modules\Patients\Models\Patient;
use Illuminate\Support\Facades\DB;

function liveVideoConsult(): array
{
    app(FeatureFlags::class)->set('video_consults', true);

    $patient = Patient::factory()->create();
    $doctor = Doctor::factory()->create();

    $consultId = test()->actingAs($patient->user)
        ->postJson('/api/consults', ['complaint' => 'Persistent migraines'])->json('id');
    test()->actingAs($doctor->user)->postJson("/api/doctor/consults/{$consultId}/accept");

    return [$patient, $doctor, $consultId];
}

it('starts a call once and lands both participants in the same room', function () {
    [$patient, $doctor, $consultId] = liveVideoConsult();

    $doctorSession = $this->actingAs($doctor->user)
        ->postJson("/api/consults/{$consultId}/video-session", ['modality' => 'video'])
        ->assertCreated()
        ->json();

    expect($doctorSession['provider'])->toBe('fake')
        ->and($doctorSession['room_url'])->toContain(strtolower($consultId))
        ->and($doctorSession['token'])->toContain('-owner') // doctor owns the room
        ->and($doctorSession['modality'])->toBe('video');

    $patientSession = $this->actingAs($patient->user)
        ->postJson("/api/consults/{$consultId}/video-session", ['modality' => 'video'])
        ->assertCreated()
        ->json();

    expect($patientSession['room_url'])->toBe($doctorSession['room_url']) // same room
        ->and($patientSession['token'])->not->toContain('-owner');

    // Exactly one "call started" system message despite two session calls.
    $messages = $this->actingAs($patient->user)->getJson("/api/consults/{$consultId}/messages")->json();
    expect(collect($messages)->filter(fn ($m) => str_contains($m['body'], 'call started'))->count())->toBe(1);
});

it('walks the ladder down: end call returns the consult to chat, room retained', function () {
    [$patient, $doctor, $consultId] = liveVideoConsult();

    $this->actingAs($doctor->user)->postJson("/api/consults/{$consultId}/video-session", ['modality' => 'voice']);
    $this->actingAs($patient->user)->postJson("/api/consults/{$consultId}/end-call")
        ->assertOk()
        ->assertJsonPath('modality', 'chat');

    $consult = Consult::find($consultId);
    expect($consult->modality)->toBe('chat')
        ->and($consult->daily_room)->not->toBeNull(); // re-upgrade reuses the room

    // The downgrade is announced in-thread, never a dead end.
    $messages = $this->actingAs($patient->user)->getJson("/api/consults/{$consultId}/messages")->json();
    expect(collect($messages)->pluck('body')->join(' '))->toContain('Back to chat');
});

it('gates calls behind the feature flag', function () {
    [, $doctor, $consultId] = liveVideoConsult();
    app(FeatureFlags::class)->set('video_consults', false);

    $this->actingAs($doctor->user)
        ->postJson("/api/consults/{$consultId}/video-session", ['modality' => 'video'])
        ->assertStatus(422);
});

it('refuses calls outside a live consult and from strangers', function () {
    [, , $consultId] = liveVideoConsult();
    $stranger = Patient::factory()->create();

    $this->actingAs($stranger->user)
        ->postJson("/api/consults/{$consultId}/video-session", ['modality' => 'video'])
        ->assertForbidden();

    Consult::where('id', $consultId)->update(['state' => Consult::STATE_CONCLUDED]);
    $doctor = Doctor::find(Consult::find($consultId)->doctor_id);
    $this->actingAs($doctor->user)
        ->postJson("/api/consults/{$consultId}/video-session", ['modality' => 'video'])
        ->assertStatus(422);
});

it('audits call start and end', function () {
    [$patient, $doctor, $consultId] = liveVideoConsult();

    $this->actingAs($doctor->user)->postJson("/api/consults/{$consultId}/video-session", ['modality' => 'video']);
    $this->actingAs($doctor->user)->postJson("/api/consults/{$consultId}/end-call");

    expect(DB::table('audit_events')->where('subject_id', $consultId)->where('event', 'consult.call_started')->exists())->toBeTrue()
        ->and(DB::table('audit_events')->where('subject_id', $consultId)->where('event', 'consult.call_ended')->exists())->toBeTrue();
});
