<?php

use App\Modules\Compliance\Services\FeatureFlags;
use App\Modules\Patients\Models\Patient;
use Illuminate\Support\Facades\DB;

it('stores and removes web push subscriptions per device', function () {
    $patient = Patient::factory()->create();

    $this->actingAs($patient->user)
        ->postJson('/api/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => ['p256dh' => 'pkey', 'auth' => 'atoken'],
        ])
        ->assertCreated();

    // Same endpoint re-registered → upsert, not duplicate.
    $this->actingAs($patient->user)
        ->postJson('/api/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => ['p256dh' => 'pkey2', 'auth' => 'atoken2'],
        ])
        ->assertCreated();

    expect(DB::table('push_subscriptions')->count())->toBe(1);

    $this->actingAs($patient->user)
        ->deleteJson('/api/push/subscribe', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123'])
        ->assertOk();
    expect(DB::table('push_subscriptions')->count())->toBe(0);
});

it('serves feature flags with unknown keys defaulting to off', function () {
    $patient = Patient::factory()->create();
    $flags = app(FeatureFlags::class);

    expect($flags->enabled('video_consults'))->toBeFalse(); // ships dark

    $flags->set('video_consults', true, 'pilot cohort');

    $this->actingAs($patient->user)
        ->getJson('/api/features')
        ->assertOk()
        ->assertJsonPath('video_consults', true);
});

it('lists device sessions and revokes others', function () {
    $patient = Patient::factory()->create();

    // Two device sign-ins (token name = device label).
    $patient->user->createToken('Tecno Spark — Chrome');
    $second = $patient->user->createToken('Infinix Note — Chrome')->plainTextToken;

    $sessions = $this->withToken($second)->getJson('/api/me/sessions')->assertOk()->json();
    expect($sessions)->toHaveCount(2)
        ->and(collect($sessions)->firstWhere('current', true)['device'])->toBe('Infinix Note — Chrome');

    $this->withToken($second)->postJson('/api/me/sessions/revoke-others')->assertOk();
    expect($patient->user->tokens()->count())->toBe(1)
        ->and($patient->user->tokens()->first()->name)->toBe('Infinix Note — Chrome');
});

it('prunes only what retention policy allows', function () {
    $patient = Patient::factory()->create();

    DB::table('otp_codes')->insert([
        'phone' => '+2348000000001', 'code_hash' => 'x', 'attempts' => 0,
        'expires_at' => now()->subDays(2), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('phi_access_log')->insert([
        ['user_id' => $patient->user_id, 'label' => 'old.read', 'ip' => '1.1.1.1', 'created_at' => now()->subMonths(30)],
        ['user_id' => $patient->user_id, 'label' => 'recent.read', 'ip' => '1.1.1.1', 'created_at' => now()->subMonths(2)],
    ]);
    DB::table('audit_events')->insert([
        'subject_type' => 'x', 'subject_id' => '1', 'event' => 'ancient.event', 'created_at' => now()->subYears(5),
    ]);

    $this->artisan('compliance:prune')->assertSuccessful();

    expect(DB::table('otp_codes')->count())->toBe(0)
        ->and(DB::table('phi_access_log')->pluck('label')->all())->toBe(['recent.read'])
        // Audit events are NEVER pruned by this job.
        ->and(DB::table('audit_events')->where('event', 'ancient.event')->exists())->toBeTrue();
});
