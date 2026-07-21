<?php

namespace App\Modules\Patients\Services;

use App\Models\User;
use App\Modules\Compliance\Services\AuditRecorder;
use App\Modules\Compliance\Services\ConsentLedger;
use App\Modules\Consults\Models\Consult;
use App\Modules\Messaging\Services\SmsSender;
use App\Modules\Patients\Models\Patient;
use App\Modules\Patients\Models\Sponsorship;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\WalletService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SponsorshipService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly ConsentLedger $consents,
        private readonly SmsSender $sms,
        private readonly AuditRecorder $audit,
    ) {
    }

    /**
     * Sponsor invites a beneficiary by Nigerian phone number. If the person has
     * never used the platform, a patient shell is created — they claim it on
     * first OTP login (same phone = same user).
     */
    public function invite(User $sponsor, string $phone, string $label): Sponsorship
    {
        return DB::transaction(function () use ($sponsor, $phone, $label) {
            $beneficiaryUser = User::firstOrCreate(
                ['phone' => $phone],
                ['name' => '', 'role' => User::ROLE_PATIENT, 'password' => Str::random(40)],
            );

            if (! $beneficiaryUser->isPatient()) {
                throw new DomainException('That phone number belongs to a non-patient account.');
            }

            $patient = Patient::firstOrCreate(['user_id' => $beneficiaryUser->id]);

            $existing = Sponsorship::where('sponsor_user_id', $sponsor->id)
                ->where('patient_id', $patient->id)
                ->first();
            if ($existing !== null) {
                return $existing; // idempotent invite
            }

            $sponsorship = Sponsorship::create([
                'sponsor_user_id' => $sponsor->id,
                'patient_id' => $patient->id,
                'status' => Sponsorship::STATUS_PENDING,
                'beneficiary_label' => $label,
            ]);

            $this->sms->send($phone, __(':sponsor wants to sponsor your healthcare on NewCo Health. Sign in with this number to accept — it costs you nothing.', [
                'sponsor' => $sponsor->name,
            ]));

            $this->audit->record($sponsorship, 'sponsorship.invited', $sponsor->id);

            return $sponsorship;
        });
    }

    /**
     * Beneficiary responds. Acceptance grants the sponsor-visibility consent —
     * the patient controls it and may revoke later without ending sponsorship.
     */
    public function respond(Sponsorship $sponsorship, bool $accept): Sponsorship
    {
        if ($sponsorship->status !== Sponsorship::STATUS_PENDING) {
            throw new DomainException('This invitation has already been answered.');
        }

        $sponsorship->update([
            'status' => $accept ? Sponsorship::STATUS_ACTIVE : Sponsorship::STATUS_DECLINED,
            'responded_at' => now(),
        ]);

        if ($accept) {
            $this->consents->grant($sponsorship->patient->user, ConsentLedger::KIND_SPONSOR_VISIBILITY);
        }

        $this->audit->record($sponsorship, $accept ? 'sponsorship.accepted' : 'sponsorship.declined', $sponsorship->patient->user_id);

        return $sponsorship->refresh();
    }

    /**
     * Try to cover a triaged consult from an active sponsor's wallet.
     * Returns the covering payment, or null (patient pays themselves).
     */
    public function tryCoverConsult(Consult $consult): ?Payment
    {
        return $this->tryCover(
            $consult->patient_id,
            (int) config('pricing.consult_price_kobo'),
            [Payment::PURPOSE_CONSULT, 'consult_id', $consult->id],
        );
    }

    /** Same, for a booked appointment awaiting payment. */
    public function tryCoverBooking(\App\Modules\Scheduling\Models\Booking $booking): ?Payment
    {
        return $this->tryCover(
            $booking->patient_id,
            (int) config('pricing.booking_price_kobo'),
            [Payment::PURPOSE_BOOKING, 'booking_id', $booking->id],
        );
    }

    /** @param array{0: string, 1: string, 2: string} $purpose [purpose, fk column, id] */
    private function tryCover(string $patientId, int $fee, array $purpose): ?Payment
    {
        [$purposeName, $column, $id] = $purpose;

        $sponsorships = Sponsorship::where('patient_id', $patientId)
            ->where('status', Sponsorship::STATUS_ACTIVE)
            ->with('sponsor')
            ->get();

        foreach ($sponsorships as $sponsorship) {
            try {
                $this->wallets->debit($sponsorship->sponsor, $fee, "{$purposeName}:{$id}");
            } catch (DomainException) {
                continue; // this sponsor's wallet can't cover it — try the next
            }

            $payment = Payment::create([
                'user_id' => $sponsorship->sponsor_user_id,
                'purpose' => $purposeName,
                $column => $id,
                'amount_kobo' => $fee,
                'currency' => 'NGN',
                'gateway' => 'wallet',
                'reference' => 'PAY-'.Str::ulid(),
                'status' => Payment::STATUS_SUCCEEDED,
                'paid_at' => now(),
                'meta' => ['sponsorship_id' => $sponsorship->id],
            ]);

            $this->audit->record($payment, 'payment.sponsored', $sponsorship->sponsor_user_id);

            return $payment;
        }

        return null;
    }

    /**
     * Sponsor's dashboard data. Clinical detail appears ONLY while the
     * beneficiary's sponsor-visibility consent is granted (policy, not UI).
     */
    public function overviewFor(User $sponsor): array
    {
        $wallet = $this->wallets->for($sponsor);

        $beneficiaries = Sponsorship::where('sponsor_user_id', $sponsor->id)
            ->with('patient.user')
            ->get()
            ->map(function (Sponsorship $s) {
                $visible = $s->status === Sponsorship::STATUS_ACTIVE
                    && $this->consents->has($s->patient->user, ConsentLedger::KIND_SPONSOR_VISIBILITY);

                $lastConsult = $visible
                    ? Consult::where('patient_id', $s->patient_id)->latest()->first()
                    : null;

                return [
                    'sponsorship_id' => $s->id,
                    'label' => $s->beneficiary_label,
                    'phone' => $s->patient->user->phone,
                    'status' => $s->status,
                    'care_visible' => $visible,
                    'last_consult' => $lastConsult === null ? null : [
                        'state' => $lastConsult->state,
                        'at' => $lastConsult->created_at->toIso8601String(),
                    ],
                ];
            });

        return [
            'wallet_balance_kobo' => $wallet->balance_kobo,
            'beneficiaries' => $beneficiaries,
        ];
    }
}
