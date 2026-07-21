<?php

namespace App\Modules\Payments\Services;

use App\Modules\Payments\Models\Payment;

/**
 * Local/testing driver: checkout succeeds instantly, webhooks are signed with
 * a shared testing secret. Lets the entire payment machine run end-to-end
 * before Paystack credentials exist.
 */
class FakeGateway implements PaymentGateway
{
    public const WEBHOOK_SECRET = 'fake-webhook-secret';

    public function name(): string
    {
        return 'fake';
    }

    public function initialise(Payment $payment): array
    {
        return ['checkout_url' => null]; // synchronous success — no redirect
    }

    public function verify(Payment $payment): string
    {
        return Payment::STATUS_SUCCEEDED;
    }

    public function webhookIsValid(string $rawBody, ?string $signature): bool
    {
        return $signature === hash_hmac('sha512', $rawBody, self::WEBHOOK_SECRET);
    }

    public function webhookEvents(array $payload): array
    {
        return [[$payload['data']['reference'] ?? '', Payment::STATUS_SUCCEEDED]];
    }
}
