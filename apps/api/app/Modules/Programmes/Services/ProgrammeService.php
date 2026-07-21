<?php

namespace App\Modules\Programmes\Services;

use App\Modules\Compliance\Services\AuditRecorder;
use App\Modules\Messaging\Services\SmsSender;
use App\Modules\Patients\Models\Patient;
use App\Modules\Patients\Services\OrganisationCoverService;
use App\Modules\Patients\Services\SponsorshipService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentGateway;
use App\Modules\Programmes\Models\Programme;
use App\Modules\Programmes\Models\ProgrammeEnrolment;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Chronic-care programmes (startup plan §4 wedge 2): doctor-led monthly
 * management with scheduled check-in nudges — retention, not episodes.
 */
class ProgrammeService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly OrganisationCoverService $organisations,
        private readonly AuditRecorder $audit,
        private readonly SmsSender $sms,
    ) {
    }

    /** Enrol and take the first month's fee (org float → sponsor wallet → self). */
    public function enrol(Patient $patient, Programme $programme): array
    {
        if (! $programme->active) {
            throw new DomainException('This programme is not open for enrolment.');
        }

        $existing = ProgrammeEnrolment::where('programme_id', $programme->id)
            ->where('patient_id', $patient->id)
            ->first();
        if ($existing !== null && $existing->status === ProgrammeEnrolment::STATUS_ACTIVE) {
            throw new DomainException('You are already enrolled in this programme.');
        }

        return DB::transaction(function () use ($patient, $programme, $existing) {
            $enrolment = $existing ?? ProgrammeEnrolment::create([
                'programme_id' => $programme->id,
                'patient_id' => $patient->id,
                'status' => ProgrammeEnrolment::STATUS_LAPSED, // activated on payment
                'current_period_ends_at' => now(),
                'next_check_in_at' => now(),
            ]);

            if (! config('pricing.payments_required')) {
                $this->activate($enrolment, $programme);

                return ['enrolment' => $enrolment->refresh(), 'checkout_url' => null];
            }

            $covered = $this->organisations->tryCover(
                $patient,
                $programme->monthly_price_kobo,
                'programme',
                'programme_enrolment_id',
                $enrolment->id,
            ) ?? app(SponsorshipService::class)->tryCoverEnrolment($enrolment, $programme->monthly_price_kobo);

            if ($covered !== null) {
                $this->activate($enrolment, $programme);

                return ['enrolment' => $enrolment->refresh(), 'checkout_url' => null];
            }

            // Self-pay through the standard machine.
            $payment = Payment::firstOrCreate(
                ['programme_enrolment_id' => $enrolment->id, 'purpose' => 'programme', 'status' => Payment::STATUS_PENDING],
                [
                    'user_id' => $patient->user_id,
                    'amount_kobo' => $programme->monthly_price_kobo,
                    'currency' => 'NGN',
                    'gateway' => $this->gateway->name(),
                    'reference' => 'PAY-'.Str::ulid(),
                ],
            );

            $checkout = $this->gateway->initialise($payment);
            if ($checkout['checkout_url'] === null && $this->gateway->verify($payment) === Payment::STATUS_SUCCEEDED) {
                $payment->update(['status' => Payment::STATUS_SUCCEEDED, 'paid_at' => now()]);
                $this->activate($enrolment, $programme);
            }

            return ['enrolment' => $enrolment->refresh(), 'checkout_url' => $checkout['checkout_url']];
        });
    }

    public function activate(ProgrammeEnrolment $enrolment, Programme $programme): void
    {
        $enrolment->update([
            'status' => ProgrammeEnrolment::STATUS_ACTIVE,
            'current_period_ends_at' => now()->addMonth(),
            'next_check_in_at' => now()->addDays($programme->check_in_every_days),
        ]);

        $this->audit->record($enrolment, 'programme.activated');
    }

    public function cancel(ProgrammeEnrolment $enrolment): void
    {
        $enrolment->update(['status' => ProgrammeEnrolment::STATUS_CANCELLED]);
        $this->audit->record($enrolment, 'programme.cancelled', $enrolment->patient->user_id);
    }

    /** Scheduled: check-in nudges + lapse expired periods. */
    public function tick(): array
    {
        $nudged = 0;
        $lapsed = 0;

        ProgrammeEnrolment::where('status', ProgrammeEnrolment::STATUS_ACTIVE)
            ->where('next_check_in_at', '<=', now())
            ->with('patient.user', 'programme')
            ->each(function (ProgrammeEnrolment $enrolment) use (&$nudged) {
                $phone = $enrolment->patient->user->phone;
                if ($phone !== null) {
                    $this->sms->send($phone, __('Time for your :programme check-in. Open the app and start a consult — it takes a few minutes.', [
                        'programme' => $enrolment->programme->name,
                    ]));
                }
                $enrolment->update([
                    'next_check_in_at' => now()->addDays($enrolment->programme->check_in_every_days),
                    'last_nudged_at' => now(),
                ]);
                $nudged++;
            });

        ProgrammeEnrolment::where('status', ProgrammeEnrolment::STATUS_ACTIVE)
            ->where('current_period_ends_at', '<', now())
            ->with('patient.user')
            ->each(function (ProgrammeEnrolment $enrolment) use (&$lapsed) {
                $enrolment->update(['status' => ProgrammeEnrolment::STATUS_LAPSED]);
                $this->audit->record($enrolment, 'programme.lapsed');

                $phone = $enrolment->patient->user->phone;
                if ($phone !== null) {
                    $this->sms->send($phone, __('Your care programme has ended. Renew in the app to keep your check-ins going.'));
                }
                $lapsed++;
            });

        return ['nudged' => $nudged, 'lapsed' => $lapsed];
    }
}
