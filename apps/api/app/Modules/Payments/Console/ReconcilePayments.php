<?php

namespace App\Modules\Payments\Console;

use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentGateway;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Nightly reconciliation (dev plan §6): re-verify stale pending payments at
 * the provider so missed webhooks never strand a paid patient un-queued.
 */
class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile';

    protected $description = 'Re-verify stale pending payments against the provider and settle them';

    public function handle(PaymentGateway $gateway, PaymentService $payments): int
    {
        $settled = 0;
        $failed = 0;
        $errors = 0;

        Payment::where('status', Payment::STATUS_PENDING)
            ->where('created_at', '<', now()->subMinutes(15))
            ->each(function (Payment $payment) use ($gateway, $payments, &$settled, &$failed, &$errors) {
                try {
                    $status = $gateway->verify($payment);
                } catch (Throwable) {
                    $errors++;

                    return;
                }

                if ($status !== Payment::STATUS_PENDING) {
                    $payments->settle($payment->reference, $status);
                    $status === Payment::STATUS_SUCCEEDED ? $settled++ : $failed++;
                }
            });

        $this->info("Reconciled — settled: {$settled}; marked failed: {$failed}; provider errors (retry tomorrow): {$errors}");

        return self::SUCCESS;
    }
}
