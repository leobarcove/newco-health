<?php

namespace App\Modules\Patients\Services;

use App\Modules\Compliance\Services\AuditRecorder;
use App\Modules\Patients\Models\Organisation;
use App\Modules\Patients\Models\OrganisationMembership;
use App\Modules\Patients\Models\Patient;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Employer/HMO cover: an active member's fee is paid from the organisation's
 * prepaid float. Checked BEFORE sponsor wallets — employer benefit first.
 */
class OrganisationCoverService
{
    public function __construct(private readonly AuditRecorder $audit)
    {
    }

    public function tryCover(Patient $patient, int $feeKobo, string $purpose, string $column, string $id): ?Payment
    {
        $organisationIds = OrganisationMembership::where('patient_id', $patient->id)
            ->where('status', OrganisationMembership::STATUS_ACTIVE)
            ->pluck('organisation_id');

        foreach ($organisationIds as $organisationId) {
            $payment = DB::transaction(function () use ($patient, $organisationId, $feeKobo, $purpose, $column, $id) {
                $organisation = Organisation::where('id', $organisationId)->lockForUpdate()->first();

                if ($organisation === null
                    || $organisation->status !== Organisation::STATUS_ACTIVE
                    || $organisation->balance_kobo < $feeKobo) {
                    return null;
                }

                $organisation->decrement('balance_kobo', $feeKobo);

                $payment = Payment::create([
                    'user_id' => $patient->user_id, // the member whose care was covered
                    'purpose' => $purpose,
                    $column => $id,
                    'amount_kobo' => $feeKobo,
                    'currency' => 'NGN',
                    'gateway' => 'organisation',
                    'reference' => 'PAY-'.Str::ulid(),
                    'status' => Payment::STATUS_SUCCEEDED,
                    'paid_at' => now(),
                    'meta' => ['organisation_id' => $organisation->id],
                ]);

                $this->audit->record($payment, 'payment.organisation_covered', context: ['organisation_id' => $organisation->id]);

                return $payment;
            });

            if ($payment !== null) {
                return $payment;
            }
        }

        return null;
    }
}
