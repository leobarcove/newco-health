<?php

namespace App\Modules\Payments\Services;

use App\Modules\Compliance\Services\AuditRecorder;
use App\Modules\Consults\Models\Consult;
use App\Modules\Consults\Services\ConsultService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payouts\Services\EarningsService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ConsultService $consults,
        private readonly EarningsService $earnings,
        private readonly AuditRecorder $audit,
    ) {
    }

    /**
     * Start (or resume) payment for a consult. Idempotent: an existing pending
     * payment is re-initialised, a succeeded one is returned as-is.
     */
    public function payForConsult(Consult $consult): Payment
    {
        if ($consult->state !== Consult::STATE_TRIAGED) {
            throw new DomainException('This consult is not awaiting payment.');
        }

        $payment = Payment::firstOrCreate(
            ['consult_id' => $consult->id, 'purpose' => Payment::PURPOSE_CONSULT],
            [
                'user_id' => $consult->patient->user_id,
                'amount_kobo' => (int) config('pricing.consult_price_kobo'),
                'currency' => 'NGN',
                'gateway' => $this->gateway->name(),
                'reference' => 'PAY-'.Str::ulid(),
            ],
        );

        if ($payment->status === Payment::STATUS_SUCCEEDED) {
            return $payment;
        }

        $checkout = $this->gateway->initialise($payment);
        $payment->update(['meta' => [...($payment->meta ?? []), 'checkout_url' => $checkout['checkout_url']]]);

        // Synchronous gateways (fake driver) complete immediately.
        if ($checkout['checkout_url'] === null) {
            $this->settle($payment->reference, $this->gateway->verify($payment));
        }

        return $payment->refresh();
    }

    /** Pay for a booking that is holding its slot pending payment. */
    public function payForBooking(\App\Modules\Scheduling\Models\Booking $booking): Payment
    {
        if ($booking->state !== \App\Modules\Scheduling\Models\Booking::STATE_PENDING_PAYMENT) {
            throw new DomainException('This booking is not awaiting payment.');
        }

        $payment = Payment::firstOrCreate(
            ['booking_id' => $booking->id, 'purpose' => Payment::PURPOSE_BOOKING],
            [
                'user_id' => $booking->patient->user_id,
                'amount_kobo' => (int) config('pricing.booking_price_kobo'),
                'currency' => 'NGN',
                'gateway' => $this->gateway->name(),
                'reference' => 'PAY-'.Str::ulid(),
            ],
        );

        if ($payment->status === Payment::STATUS_SUCCEEDED) {
            return $payment;
        }

        $checkout = $this->gateway->initialise($payment);
        $payment->update(['meta' => [...($payment->meta ?? []), 'checkout_url' => $checkout['checkout_url']]]);

        if ($checkout['checkout_url'] === null) {
            $this->settle($payment->reference, $this->gateway->verify($payment));
        }

        return $payment->refresh();
    }

    /** Sponsor tops up their care wallet by an amount of their choosing. */
    public function topUpWallet(\App\Models\User $user, int $amountKobo): Payment
    {
        $payment = Payment::create([
            'user_id' => $user->id,
            'purpose' => Payment::PURPOSE_WALLET_TOPUP,
            'amount_kobo' => $amountKobo,
            'currency' => 'NGN',
            'gateway' => $this->gateway->name(),
            'reference' => 'PAY-'.Str::ulid(),
        ]);

        $checkout = $this->gateway->initialise($payment);
        $payment->update(['meta' => ['checkout_url' => $checkout['checkout_url']]]);

        if ($checkout['checkout_url'] === null) {
            $this->settle($payment->reference, $this->gateway->verify($payment));
        }

        return $payment->refresh();
    }

    /**
     * Settle a payment by reference — the single entry point used by webhooks,
     * verify polling, and synchronous gateways. Idempotent by design: a payment
     * only ever moves out of `pending` once.
     */
    public function settle(string $reference, string $status): ?Payment
    {
        return DB::transaction(function () use ($reference, $status) {
            $payment = Payment::where('reference', $reference)->lockForUpdate()->first();

            if ($payment === null || $payment->status !== Payment::STATUS_PENDING) {
                return $payment; // unknown or already settled — never double-apply
            }

            if ($status === Payment::STATUS_SUCCEEDED) {
                $payment->update(['status' => Payment::STATUS_SUCCEEDED, 'paid_at' => now()]);
                $this->audit->record($payment, 'payment.succeeded', context: ['amount_kobo' => $payment->amount_kobo]);

                if ($payment->purpose === Payment::PURPOSE_CONSULT && $payment->consult !== null) {
                    $this->consults->queueAfterPayment($payment->consult);
                } elseif ($payment->purpose === Payment::PURPOSE_BOOKING && $payment->booking_id !== null) {
                    app(\App\Modules\Scheduling\Services\BookingService::class)
                        ->confirmAfterPayment(\App\Modules\Scheduling\Models\Booking::findOrFail($payment->booking_id));
                } elseif ($payment->purpose === Payment::PURPOSE_WALLET_TOPUP) {
                    app(\App\Modules\Payments\Services\WalletService::class)
                        ->credit($payment->user, $payment->amount_kobo, "topup:{$payment->reference}");
                }
            } elseif ($status === Payment::STATUS_FAILED) {
                $payment->update(['status' => Payment::STATUS_FAILED]);
                $this->audit->record($payment, 'payment.failed');
            }

            return $payment;
        });
    }

    /** Handle a raw provider webhook. Returns handled event count. */
    public function handleWebhook(string $rawBody, ?string $signature): int
    {
        if (! $this->gateway->webhookIsValid($rawBody, $signature)) {
            throw new DomainException('Invalid webhook signature.');
        }

        $payload = json_decode($rawBody, true) ?? [];
        $handled = 0;

        foreach ($this->gateway->webhookEvents($payload) as [$reference, $status]) {
            if ($reference !== '' && $this->settle($reference, $status) !== null) {
                $handled++;
            }
        }

        return $handled;
    }

    /**
     * Staff refund. Wallet-sponsored payments return to the sponsor's wallet;
     * gateway payments refund at the provider. Refunding a not-yet-seen
     * consult abandons it; refunding a booking cancels it (staff bypass).
     */
    public function refund(Payment $payment, int $actorId, string $reason): Payment
    {
        if ($payment->status !== Payment::STATUS_SUCCEEDED) {
            throw new DomainException('Only settled payments can be refunded.');
        }

        return DB::transaction(function () use ($payment, $actorId, $reason) {
            $payment = Payment::where('id', $payment->id)->lockForUpdate()->first();
            if ($payment->status !== Payment::STATUS_SUCCEEDED) {
                return $payment; // raced — already refunded
            }

            if ($payment->gateway === 'wallet') {
                app(\App\Modules\Payments\Services\WalletService::class)
                    ->credit($payment->user, $payment->amount_kobo, "refund:{$payment->reference}");
            } elseif (! $this->gateway->refund($payment)) {
                throw new DomainException('The payment provider rejected the refund.');
            }

            $payment->update(['status' => Payment::STATUS_REFUNDED, 'meta' => [...($payment->meta ?? []), 'refund_reason' => $reason]]);
            $this->audit->record($payment, 'payment.refunded', $actorId, ['reason' => $reason]);

            // Unwind whatever the payment was buying, where that still makes sense.
            if ($payment->purpose === Payment::PURPOSE_CONSULT && $payment->consult !== null) {
                $consult = $payment->consult;
                if (in_array($consult->state, [\App\Modules\Consults\Models\Consult::STATE_TRIAGED, \App\Modules\Consults\Models\Consult::STATE_QUEUED], true)) {
                    app(\App\Modules\Consults\Services\ConsultStateMachine::class)
                        ->transition($consult, \App\Modules\Consults\Models\Consult::STATE_ABANDONED, $actorId);
                }
            } elseif ($payment->purpose === Payment::PURPOSE_BOOKING && $payment->booking_id !== null) {
                $booking = \App\Modules\Scheduling\Models\Booking::find($payment->booking_id);
                if ($booking !== null && in_array($booking->state, \App\Modules\Scheduling\Models\Booking::SLOT_HOLDING_STATES, true)) {
                    app(\App\Modules\Scheduling\Services\BookingService::class)->cancel($booking, 'staff', $actorId);
                }
            }

            return $payment->refresh();
        });
    }

    /** Credit the doctor's share once a paid consult concludes. */
    public function creditDoctorForConcludedConsult(Consult $consult): void
    {
        $paid = Payment::where('consult_id', $consult->id)
            ->where('status', Payment::STATUS_SUCCEEDED)
            ->first();

        if ($paid !== null && $consult->doctor_id !== null) {
            $this->earnings->credit($consult, $paid->amount_kobo);
        }
    }
}
