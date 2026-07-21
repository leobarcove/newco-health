<?php

namespace App\Modules\Payouts\Services;

use App\Modules\Compliance\Services\AuditRecorder;
use App\Modules\Doctors\Models\Doctor;
use App\Modules\Payments\Services\PaymentGateway;
use App\Modules\Payouts\Models\DoctorEarning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Weekly payout run — the doctor-side moat is FAST, RELIABLE payouts
 * (startup plan §4 wedge 3). One transfer per doctor per run.
 */
class PayoutService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly AuditRecorder $audit,
    ) {
    }

    /** @return array{paid: int, skipped_no_bank: int, failed: int} */
    public function run(): array
    {
        $result = ['paid' => 0, 'skipped_no_bank' => 0, 'failed' => 0];

        $doctorIds = DoctorEarning::where('status', DoctorEarning::STATUS_PENDING)
            ->distinct()
            ->pluck('doctor_id');

        foreach ($doctorIds as $doctorId) {
            $doctor = Doctor::find($doctorId);

            if ($doctor?->paystack_recipient_code === null) {
                $result['skipped_no_bank']++; // stays pending — never lost, just waiting for bank details

                continue;
            }

            DB::transaction(function () use ($doctor, &$result) {
                $earnings = DoctorEarning::where('doctor_id', $doctor->id)
                    ->where('status', DoctorEarning::STATUS_PENDING)
                    ->lockForUpdate()
                    ->get();

                $total = (int) $earnings->sum('amount_kobo');
                if ($total === 0) {
                    return;
                }

                $reference = 'PO-'.Str::ulid();

                if (! $this->gateway->transfer($total, $reference, $doctor->paystack_recipient_code)) {
                    $result['failed']++; // earnings stay pending; retried next run

                    return;
                }

                DoctorEarning::whereIn('id', $earnings->pluck('id'))->update([
                    'status' => DoctorEarning::STATUS_PAID,
                    'payout_reference' => $reference,
                    'paid_at' => now(),
                ]);

                $this->audit->record($doctor, 'payout.paid', context: [
                    'reference' => $reference,
                    'amount_kobo' => $total,
                    'earnings' => $earnings->count(),
                ]);

                $result['paid']++;
            });
        }

        return $result;
    }
}
