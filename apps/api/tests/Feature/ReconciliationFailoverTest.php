<?php

use App\Models\User;
use App\Modules\Consults\Models\Consult;
use App\Modules\Patients\Models\Patient;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\FailoverGateway;
use App\Modules\Payments\Services\PaymentGateway;

/** A scriptable gateway for chain/failover behaviour tests. */
function scriptedGateway(string $name, bool $initialiseThrows = false, string $verifyResult = Payment::STATUS_SUCCEEDED): PaymentGateway
{
    return new class($name, $initialiseThrows, $verifyResult) implements PaymentGateway
    {
        public function __construct(
            private string $gatewayName,
            private bool $initialiseThrows,
            private string $verifyResult,
        ) {
        }

        public function name(): string
        {
            return $this->gatewayName;
        }

        public function initialise(Payment $payment): array
        {
            if ($this->initialiseThrows) {
                throw new RuntimeException("{$this->gatewayName} is down");
            }

            return ['checkout_url' => "https://{$this->gatewayName}.example/checkout"];
        }

        public function verify(Payment $payment): string
        {
            return $this->verifyResult;
        }

        public function webhookIsValid(string $rawBody, ?string $signature): bool
        {
            return $signature === $this->gatewayName;
        }

        public function webhookEvents(array $payload): array
        {
            return [];
        }

        public function refund(Payment $payment): bool
        {
            return true;
        }

        public function transfer(int $amountKobo, string $reference, string $recipientCode): bool
        {
            return true;
        }
    };
}

function pendingPayment(): Payment
{
    $patient = Patient::factory()->create();

    return Payment::create([
        'user_id' => $patient->user_id,
        'purpose' => Payment::PURPOSE_CONSULT,
        'consult_id' => Consult::factory()->create(['patient_id' => $patient->id, 'state' => Consult::STATE_TRIAGED])->id,
        'amount_kobo' => 250000,
        'currency' => 'NGN',
        'gateway' => 'fake',
        'reference' => 'PAY-'.\Illuminate\Support\Str::ulid(),
        'status' => Payment::STATUS_PENDING,
    ]);
}

// ——— Failover ———

it('fails over to the secondary gateway when the primary cannot initialise', function () {
    $failover = new FailoverGateway(
        scriptedGateway('paystack', initialiseThrows: true),
        scriptedGateway('flutterwave'),
    );
    $payment = pendingPayment();

    $checkout = $failover->initialise($payment);

    expect($checkout['checkout_url'])->toContain('flutterwave')
        ->and($payment->refresh()->gateway)->toBe('flutterwave'); // ownership recorded for life

    // Webhook signatures from either provider are accepted.
    expect($failover->webhookIsValid('body', 'paystack'))->toBeTrue()
        ->and($failover->webhookIsValid('body', 'flutterwave'))->toBeTrue()
        ->and($failover->webhookIsValid('body', 'nonsense'))->toBeFalse();
});

it('uses the primary when it is healthy', function () {
    $failover = new FailoverGateway(scriptedGateway('paystack'), scriptedGateway('flutterwave'));
    $payment = pendingPayment();

    expect($failover->initialise($payment)['checkout_url'])->toContain('paystack')
        ->and($payment->refresh()->gateway)->toBe('fake'); // unchanged — primary owns it
});

// ——— Nightly reconciliation ———

it('settles stale pending payments on reconcile, queueing the consult', function () {
    config(['pricing.payments_required' => true]);
    $payment = pendingPayment();
    Payment::where('id', $payment->id)->update(['created_at' => now()->subHour()]);

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($payment->refresh()->status)->toBe(Payment::STATUS_SUCCEEDED) // fake gateway verifies as succeeded
        ->and(Consult::find($payment->consult_id)->state)->toBe(Consult::STATE_QUEUED);
});

it('leaves recent pending payments alone (the customer may still be at checkout)', function () {
    $payment = pendingPayment(); // just created

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($payment->refresh()->status)->toBe(Payment::STATUS_PENDING);
});

// ——— Sponsor TOTP ———

it('enforces totp on sponsor login once enrolled', function () {
    $sponsor = User::create([
        'name' => 'Ade', 'email' => 'ade-2fa@example.com', 'password' => 'a-long-password', 'role' => User::ROLE_SPONSOR,
    ]);

    $google2fa = app(\PragmaRX\Google2FA\Google2FA::class);

    // Setup returns a secret; enable requires proving possession.
    $secret = $this->actingAs($sponsor)->postJson('/api/sponsor/2fa/setup')->assertOk()->json('secret');

    $this->actingAs($sponsor)
        ->postJson('/api/sponsor/2fa/enable', ['secret' => $secret, 'code' => '000000'])
        ->assertStatus(422); // wrong code — not enabled

    $this->actingAs($sponsor)
        ->postJson('/api/sponsor/2fa/enable', ['secret' => $secret, 'code' => $google2fa->getCurrentOtp($secret)])
        ->assertOk();

    // Password alone no longer signs in.
    $this->postJson('/api/auth/sponsor/login', ['email' => 'ade-2fa@example.com', 'password' => 'a-long-password'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'otp_required');

    // Password + current TOTP does.
    $this->postJson('/api/auth/sponsor/login', [
        'email' => 'ade-2fa@example.com',
        'password' => 'a-long-password',
        'otp' => $google2fa->getCurrentOtp($secret),
    ])->assertOk();
});
