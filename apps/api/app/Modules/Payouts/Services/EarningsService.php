<?php

namespace App\Modules\Payouts\Services;

use App\Modules\Compliance\Services\AuditRecorder;
use App\Modules\Consults\Models\Consult;
use App\Modules\Doctors\Models\Doctor;
use App\Modules\Payouts\Models\DoctorEarning;

class EarningsService
{
    public function __construct(private readonly AuditRecorder $audit)
    {
    }

    /** One earning per consult — the unique index makes double-credit impossible. */
    public function credit(Consult $consult, int $grossKobo): DoctorEarning
    {
        $share = intdiv($grossKobo * (int) config('pricing.doctor_share_percent'), 100);

        $earning = DoctorEarning::firstOrCreate(
            ['consult_id' => $consult->id],
            [
                'doctor_id' => $consult->doctor_id,
                'amount_kobo' => $share,
                'status' => DoctorEarning::STATUS_PENDING,
            ],
        );

        if ($earning->wasRecentlyCreated) {
            $this->audit->record($earning, 'earning.credited', context: ['amount_kobo' => $share]);
        }

        return $earning;
    }

    /** @return array{pending_kobo: int, paid_kobo: int, recent: \Illuminate\Support\Collection} */
    public function summaryFor(Doctor $doctor): array
    {
        $earnings = DoctorEarning::where('doctor_id', $doctor->id);

        return [
            'pending_kobo' => (int) (clone $earnings)->where('status', DoctorEarning::STATUS_PENDING)->sum('amount_kobo'),
            'paid_kobo' => (int) (clone $earnings)->where('status', DoctorEarning::STATUS_PAID)->sum('amount_kobo'),
            'recent' => (clone $earnings)->latest()->limit(20)->get(),
        ];
    }
}
