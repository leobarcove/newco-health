<?php

use App\Modules\Compliance\Services\ConsentLedger;
use App\Modules\Doctors\Models\Doctor;
use App\Modules\Patients\Models\Patient;
use Illuminate\Support\Facades\DB;

it('requires telemedicine consent before the first consult (HTTP 428)', function () {
    $patient = Patient::factory()->withoutConsents()->create();

    $this->actingAs($patient->user)
        ->postJson('/api/consults', ['complaint' => 'Fever since yesterday'])
        ->assertStatus(428)
        ->assertJsonPath('code', 'consent_required');

    // Grant, then it works.
    $this->actingAs($patient->user)
        ->postJson('/api/consents', ['kind' => ConsentLedger::KIND_TELEMEDICINE_TERMS, 'granted' => true])
        ->assertCreated()
        ->assertJsonPath(ConsentLedger::KIND_TELEMEDICINE_TERMS, true);

    $this->actingAs($patient->user)
        ->postJson('/api/consults', ['complaint' => 'Fever since yesterday'])
        ->assertCreated();
});

it('treats the consent ledger as append-only: revocation is a new event', function () {
    $patient = Patient::factory()->create(); // factory grants 2 consents

    $this->actingAs($patient->user)
        ->postJson('/api/consents', ['kind' => ConsentLedger::KIND_TELEMEDICINE_TERMS, 'granted' => false])
        ->assertCreated()
        ->assertJsonPath(ConsentLedger::KIND_TELEMEDICINE_TERMS, false);

    // 2 grants (factory) + 1 revoke — nothing deleted or updated.
    expect(DB::table('consents')->where('user_id', $patient->user_id)->count())->toBe(3);

    // And consults are blocked again after withdrawal.
    $this->actingAs($patient->user)
        ->postJson('/api/consults', ['complaint' => 'Cough'])
        ->assertStatus(428);
});

it('logs every PHI read with actor, subject, and label', function () {
    $patient = Patient::factory()->create();
    $doctor = Doctor::factory()->create();

    $consultId = $this->actingAs($patient->user)
        ->postJson('/api/consults', ['complaint' => 'Stomach ache'])
        ->json('id');
    $this->actingAs($doctor->user)->postJson("/api/doctor/consults/{$consultId}/accept");

    $this->actingAs($doctor->user)->getJson("/api/consults/{$consultId}/messages")->assertOk();
    $this->actingAs($patient->user)->getJson("/api/consults/{$consultId}")->assertOk();

    $rows = DB::table('phi_access_log')->get();
    expect($rows->where('label', 'consult.messages.read')->where('user_id', $doctor->user_id))->toHaveCount(1)
        ->and($rows->where('label', 'consult.read')->where('user_id', $patient->user_id))->toHaveCount(1)
        ->and($rows->first()->subject_id)->toBe($consultId);
});

it('does not log denied PHI reads as access', function () {
    $patient = Patient::factory()->create();
    $stranger = Patient::factory()->create();

    $consultId = $this->actingAs($patient->user)
        ->postJson('/api/consults', ['complaint' => 'Rash'])
        ->json('id');

    $this->actingAs($stranger->user)->getJson("/api/consults/{$consultId}/messages")->assertForbidden();

    expect(DB::table('phi_access_log')->where('user_id', $stranger->user_id)->count())->toBe(0);
});

it('sends security headers on every response', function () {
    $response = $this->getJson('/up');

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});
