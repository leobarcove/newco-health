<?php

use App\Models\User;
use App\Modules\Identity\Models\OtpCode;
use Illuminate\Support\Facades\Hash;

it('sends an otp for a valid nigerian phone number', function () {
    $this->postJson('/api/auth/otp/request', ['phone' => '+2348012345678'])
        ->assertOk();

    expect(OtpCode::where('phone', '+2348012345678')->exists())->toBeTrue();
});

it('rejects non-nigerian phone formats', function () {
    $this->postJson('/api/auth/otp/request', ['phone' => '+15551234567'])
        ->assertStatus(422);
});

it('verifies a correct code, creates the user + patient, and returns a token', function () {
    OtpCode::create([
        'phone' => '+2348012345678',
        'code_hash' => Hash::make('123456'),
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/auth/otp/verify', [
        'phone' => '+2348012345678',
        'code' => '123456',
    ])->assertOk();

    expect($response->json('token'))->toBeString()
        ->and($response->json('user.role'))->toBe('patient');

    $user = User::where('phone', '+2348012345678')->first();
    expect($user)->not->toBeNull()
        ->and($user->patient)->not->toBeNull();
});

it('rejects a wrong code and counts the attempt', function () {
    OtpCode::create([
        'phone' => '+2348012345678',
        'code_hash' => Hash::make('123456'),
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->postJson('/api/auth/otp/verify', [
        'phone' => '+2348012345678',
        'code' => '999999',
    ])->assertStatus(422);

    expect(OtpCode::where('phone', '+2348012345678')->first()->attempts)->toBe(1);
});

it('rejects an expired code', function () {
    OtpCode::create([
        'phone' => '+2348012345678',
        'code_hash' => Hash::make('123456'),
        'expires_at' => now()->subMinute(),
    ]);

    $this->postJson('/api/auth/otp/verify', [
        'phone' => '+2348012345678',
        'code' => '123456',
    ])->assertStatus(422);
});
