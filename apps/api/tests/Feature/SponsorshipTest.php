<?php

use App\Models\User;
use App\Modules\Consults\Models\Consult;
use App\Modules\Patients\Models\Patient;
use App\Modules\Patients\Models\Sponsorship;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\Wallet;

function makeSponsor(): User
{
    return User::create([
        'name' => 'Ade in London',
        'email' => 'ade@example.co.uk',
        'password' => 'a-very-long-password',
        'role' => User::ROLE_SPONSOR,
    ]);
}

it('registers and signs in a sponsor by email', function () {
    $token = $this->postJson('/api/auth/sponsor/register', [
        'name' => 'Ngozi in Houston',
        'email' => 'ngozi@example.com',
        'password' => 'correct-horse-battery',
    ])->assertCreated()->json('token');

    expect($token)->toBeString();

    $this->postJson('/api/auth/sponsor/login', [
        'email' => 'ngozi@example.com',
        'password' => 'correct-horse-battery',
    ])->assertOk()->assertJsonPath('user.role', 'sponsor');

    $this->postJson('/api/auth/sponsor/login', [
        'email' => 'ngozi@example.com',
        'password' => 'wrong',
    ])->assertStatus(422);
});

it('invites a beneficiary by phone, who accepts and grants visibility consent', function () {
    $sponsor = makeSponsor();
    $beneficiary = Patient::factory()->create();
    $phone = $beneficiary->user->phone;

    $sponsorshipId = $this->actingAs($sponsor)
        ->postJson('/api/sponsor/beneficiaries', ['phone' => $phone, 'label' => 'Mum'])
        ->assertCreated()
        ->assertJsonPath('status', 'pending')
        ->json('id');

    // Idempotent: same invite returns the same sponsorship.
    $this->actingAs($sponsor)
        ->postJson('/api/sponsor/beneficiaries', ['phone' => $phone, 'label' => 'Mum'])
        ->assertCreated()
        ->assertJsonPath('id', $sponsorshipId);

    // Beneficiary sees and accepts it.
    $this->actingAs($beneficiary->user)
        ->getJson('/api/sponsorships')
        ->assertJsonPath('0.sponsor_name', 'Ade in London');

    $this->actingAs($beneficiary->user)
        ->postJson("/api/sponsorships/{$sponsorshipId}/respond", ['accept' => true])
        ->assertOk()
        ->assertJsonPath('status', 'active');
});

it('creates a patient shell when inviting a phone number that has never signed up', function () {
    $sponsor = makeSponsor();

    $this->actingAs($sponsor)
        ->postJson('/api/sponsor/beneficiaries', ['phone' => '+2348099887766', 'label' => 'Papa'])
        ->assertCreated();

    $user = User::where('phone', '+2348099887766')->first();
    expect($user)->not->toBeNull()
        ->and($user->patient)->not->toBeNull();
});

it('tops up the sponsor wallet through the payment machine', function () {
    $sponsor = makeSponsor();

    $this->actingAs($sponsor)
        ->postJson('/api/sponsor/wallet/topup', ['amount_kobo' => 1_000_000]) // ₦10,000
        ->assertCreated()
        ->assertJsonPath('status', 'succeeded');

    expect(Wallet::where('user_id', $sponsor->id)->first()->balance_kobo)->toBe(1_000_000);
});

it('auto-covers a beneficiary consult from the sponsor wallet', function () {
    config(['pricing.payments_required' => true]);

    $sponsor = makeSponsor();
    $beneficiary = Patient::factory()->create();

    $sponsorshipId = $this->actingAs($sponsor)
        ->postJson('/api/sponsor/beneficiaries', ['phone' => $beneficiary->user->phone, 'label' => 'Mum'])
        ->json('id');
    $this->actingAs($beneficiary->user)->postJson("/api/sponsorships/{$sponsorshipId}/respond", ['accept' => true]);
    $this->actingAs($sponsor)->postJson('/api/sponsor/wallet/topup', ['amount_kobo' => 500_000]);

    // Beneficiary starts a consult — queued immediately, no payment screen.
    $this->actingAs($beneficiary->user)
        ->postJson('/api/consults', ['complaint' => 'Dizziness in the mornings'])
        ->assertCreated()
        ->assertJsonPath('state', Consult::STATE_QUEUED);

    expect(Wallet::where('user_id', $sponsor->id)->first()->balance_kobo)->toBe(250_000) // 500k − 250k fee
        ->and(Payment::where('gateway', 'wallet')->where('status', 'succeeded')->count())->toBe(1);
});

it('falls back to patient self-payment when the wallet cannot cover the fee', function () {
    config(['pricing.payments_required' => true]);

    $sponsor = makeSponsor();
    $beneficiary = Patient::factory()->create();

    $sponsorshipId = $this->actingAs($sponsor)
        ->postJson('/api/sponsor/beneficiaries', ['phone' => $beneficiary->user->phone, 'label' => 'Mum'])
        ->json('id');
    $this->actingAs($beneficiary->user)->postJson("/api/sponsorships/{$sponsorshipId}/respond", ['accept' => true]);
    $this->actingAs($sponsor)->postJson('/api/sponsor/wallet/topup', ['amount_kobo' => 100_000]); // < fee

    $this->actingAs($beneficiary->user)
        ->postJson('/api/consults', ['complaint' => 'Toothache'])
        ->assertCreated()
        ->assertJsonPath('state', Consult::STATE_TRIAGED); // awaiting the patient's own payment

    expect(Wallet::where('user_id', $sponsor->id)->first()->balance_kobo)->toBe(100_000); // untouched
});

it('shows care status to the sponsor only while visibility consent is granted', function () {
    $sponsor = makeSponsor();
    $beneficiary = Patient::factory()->create();

    $sponsorshipId = $this->actingAs($sponsor)
        ->postJson('/api/sponsor/beneficiaries', ['phone' => $beneficiary->user->phone, 'label' => 'Mum'])
        ->json('id');
    $this->actingAs($beneficiary->user)->postJson("/api/sponsorships/{$sponsorshipId}/respond", ['accept' => true]);
    $this->actingAs($beneficiary->user)->postJson('/api/consults', ['complaint' => 'Knee pain']);

    $overview = $this->actingAs($sponsor)->getJson('/api/sponsor/overview')->assertOk()->json();
    expect($overview['beneficiaries'][0]['care_visible'])->toBeTrue()
        ->and($overview['beneficiaries'][0]['last_consult'])->not->toBeNull();

    // Beneficiary withdraws visibility — sponsorship stays active, detail disappears.
    $this->actingAs($beneficiary->user)
        ->postJson('/api/consents', ['kind' => 'sponsor_visibility', 'granted' => false]);

    $overview = $this->actingAs($sponsor)->getJson('/api/sponsor/overview')->json();
    expect($overview['beneficiaries'][0]['care_visible'])->toBeFalse()
        ->and($overview['beneficiaries'][0]['last_consult'])->toBeNull()
        ->and($overview['beneficiaries'][0]['status'])->toBe(Sponsorship::STATUS_ACTIVE);
});

it('keeps patients out of sponsor endpoints and vice versa', function () {
    $patient = Patient::factory()->create();
    $sponsor = makeSponsor();

    $this->actingAs($patient->user)->getJson('/api/sponsor/overview')->assertForbidden();
    $this->actingAs($sponsor)->postJson('/api/consults', ['complaint' => 'x'])->assertForbidden();
});

it('handles dependants: add, consult for, and ownership guard', function () {
    $patient = Patient::factory()->create();
    $stranger = Patient::factory()->create();

    $dependantId = $this->actingAs($patient->user)
        ->postJson('/api/dependants', ['name' => 'Mama Ronke', 'relationship' => 'parent'])
        ->assertCreated()
        ->json('id');

    $this->actingAs($patient->user)->getJson('/api/dependants')->assertJsonCount(1);

    // Consult on behalf of the dependant.
    $this->actingAs($patient->user)
        ->postJson('/api/consults', ['complaint' => 'Mum has a fever', 'dependant_id' => $dependantId])
        ->assertCreated()
        ->assertJsonPath('for_dependant.name', 'Mama Ronke');

    // A stranger cannot use someone else's dependant.
    $this->actingAs($stranger->user)
        ->postJson('/api/consults', ['complaint' => 'x', 'dependant_id' => $dependantId])
        ->assertForbidden();

    // Nor delete them.
    $this->actingAs($stranger->user)->deleteJson("/api/dependants/{$dependantId}")->assertForbidden();
});
