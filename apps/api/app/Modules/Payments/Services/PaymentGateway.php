<?php

namespace App\Modules\Payments\Services;

use App\Modules\Payments\Models\Payment;

/**
 * The single seam to payment providers. Paystack is primary, Flutterwave
 * failover (dev plan §6) — both implement this; nothing outside the Payments
 * module may reference a provider by name.
 */
interface PaymentGateway
{
    /** Provider identifier stored on the payment row. */
    public function name(): string;

    /**
     * Start a checkout. Returns ['checkout_url' => ?string] — null when the
     * gateway completes synchronously (fake driver).
     */
    public function initialise(Payment $payment): array;

    /** Re-query the provider for a payment's authoritative status. */
    public function verify(Payment $payment): string;

    /** Validate a webhook's signature against the raw request body. */
    public function webhookIsValid(string $rawBody, ?string $signature): bool;

    /** Extract [reference, status] pairs from a verified webhook payload. */
    public function webhookEvents(array $payload): array;

    /** Refund a settled payment at the provider. Returns success. */
    public function refund(Payment $payment): bool;

    /** Send money out (doctor payouts). Returns success. */
    public function transfer(int $amountKobo, string $reference, string $recipientCode): bool;
}
