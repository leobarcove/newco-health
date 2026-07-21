<?php

namespace App\Modules\Payments\Services;

use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Paystack-primary / Flutterwave-failover (dev plan §6): when the primary
 * cannot INITIALISE a checkout, the secondary takes the payment. Verification,
 * webhooks, refunds and transfers route to whichever gateway owns the payment
 * (recorded on the row), so a failed-over payment stays consistent for life.
 */
class FailoverGateway implements PaymentGateway
{
    public function __construct(
        private readonly PaymentGateway $primary,
        private readonly PaymentGateway $secondary,
    ) {
    }

    public function name(): string
    {
        return $this->primary->name();
    }

    public function initialise(Payment $payment): array
    {
        try {
            return $this->primary->initialise($payment);
        } catch (Throwable $e) {
            Log::warning('payments.failover', ['from' => $this->primary->name(), 'to' => $this->secondary->name(), 'error' => $e->getMessage()]);

            $checkout = $this->secondary->initialise($payment);
            $payment->update(['gateway' => $this->secondary->name()]);

            return $checkout;
        }
    }

    public function verify(Payment $payment): string
    {
        return $this->owner($payment)->verify($payment);
    }

    public function webhookIsValid(string $rawBody, ?string $signature): bool
    {
        return $this->primary->webhookIsValid($rawBody, $signature)
            || $this->secondary->webhookIsValid($rawBody, $signature);
    }

    public function webhookEvents(array $payload): array
    {
        $events = $this->primary->webhookEvents($payload);

        return $events !== [] ? $events : $this->secondary->webhookEvents($payload);
    }

    public function refund(Payment $payment): bool
    {
        return $this->owner($payment)->refund($payment);
    }

    public function transfer(int $amountKobo, string $reference, string $recipientCode): bool
    {
        return $this->primary->transfer($amountKobo, $reference, $recipientCode);
    }

    private function owner(Payment $payment): PaymentGateway
    {
        return $payment->gateway === $this->secondary->name() ? $this->secondary : $this->primary;
    }
}
