<?php

namespace App\Modules\Consults\Services;

use App\Modules\Consults\Models\Consult;
use App\Modules\Consults\Models\ConsultMessage;
use App\Modules\Consults\Models\TriageIntake;
use App\Modules\Doctors\Models\Doctor;
use App\Modules\Patients\Models\Patient;
use Illuminate\Support\Facades\DB;

class ConsultService
{
    public function __construct(private readonly ConsultStateMachine $stateMachine)
    {
    }

    /**
     * Create a consult from a triage intake. Red-flag intakes are escalated
     * immediately — they never enter the normal queue.
     */
    public function createFromIntake(Patient $patient, string $complaint, array $answers, ?string $dependantId = null): Consult
    {
        return DB::transaction(function () use ($patient, $complaint, $answers, $dependantId) {
            $redFlag = collect($answers)
                ->filter(fn ($value, $key) => in_array($key, TriageIntake::RED_FLAGS, true) && $value === true)
                ->isNotEmpty();

            $intake = TriageIntake::create([
                'patient_id' => $patient->id,
                'complaint' => $complaint,
                'answers' => $answers,
                'red_flag' => $redFlag,
            ]);

            $consult = Consult::create([
                'patient_id' => $patient->id,
                'dependant_id' => $dependantId,
                'triage_intake_id' => $intake->id,
                'state' => Consult::STATE_REQUESTED,
            ]);

            $this->stateMachine->transition($consult, Consult::STATE_TRIAGED, $patient->user_id);

            if ($redFlag) {
                // Emergencies are never gated behind payment.
                $this->stateMachine->transition($consult, Consult::STATE_ESCALATED, $patient->user_id);
                $this->systemMessage($consult, __('Your symptoms need urgent in-person care. Please go to the nearest hospital now. Our team has been alerted.'));
            } elseif (config('pricing.payments_required')) {
                // An active sponsor's wallet may cover the fee silently.
                $covered = app(\App\Modules\Patients\Services\SponsorshipService::class)->tryCoverConsult($consult);

                if ($covered !== null) {
                    $this->stateMachine->transition($consult, Consult::STATE_QUEUED, $patient->user_id);
                    $this->systemMessage($consult, __('This consult is covered by your sponsor. You are in the queue — a doctor will be with you shortly.'));
                } else {
                    $this->systemMessage($consult, __('Complete payment to join the queue — the fee is shown before you pay.'));
                }
            } else {
                $this->stateMachine->transition($consult, Consult::STATE_QUEUED, $patient->user_id);
                $this->systemMessage($consult, __('You are in the queue. A doctor will be with you shortly — we will notify you.'));
            }

            return $consult->refresh();
        });
    }

    /** Called by the Payments module once the consult fee settles. */
    public function queueAfterPayment(Consult $consult): Consult
    {
        if ($consult->state !== Consult::STATE_TRIAGED) {
            return $consult; // already queued/escalated — settle() is idempotent upstream
        }

        $this->stateMachine->transition($consult, Consult::STATE_QUEUED, $consult->patient->user_id);
        $this->systemMessage($consult, __('Payment received. You are in the queue — a doctor will be with you shortly.'));

        return $consult->refresh();
    }

    /** Doctor accepts the oldest queued consult (or a specific one). */
    public function accept(Doctor $doctor, Consult $consult): Consult
    {
        if (! $doctor->canConsult()) {
            throw new \DomainException('Doctor is not eligible to consult (status or licence).');
        }

        return DB::transaction(function () use ($doctor, $consult) {
            $consult->doctor_id = $doctor->id;
            $this->stateMachine->transition($consult, Consult::STATE_ASSIGNED, $doctor->user_id);
            $this->stateMachine->transition($consult, Consult::STATE_IN_CONSULT, $doctor->user_id);
            $this->systemMessage($consult, __('Dr :name has joined your consult.', ['name' => $doctor->user->name]));

            return $consult->refresh();
        });
    }

    public function conclude(Consult $consult, int $actorId): Consult
    {
        $this->stateMachine->transition($consult, Consult::STATE_CONCLUDED, $actorId);
        $this->systemMessage($consult, __('This consult has ended. You can reply here for the next 72 hours if you have follow-up questions.'));

        return $consult->refresh();
    }

    /** Queue position: 1-based rank among queued consults by queue time. */
    public function queuePosition(Consult $consult): ?int
    {
        if ($consult->state !== Consult::STATE_QUEUED) {
            return null;
        }

        return Consult::where('state', Consult::STATE_QUEUED)
            ->where('queued_at', '<=', $consult->queued_at)
            ->count();
    }

    public function systemMessage(Consult $consult, string $body): ConsultMessage
    {
        return ConsultMessage::create([
            'consult_id' => $consult->id,
            'sender_id' => null,
            'kind' => ConsultMessage::KIND_SYSTEM,
            'body' => $body,
        ]);
    }
}
