<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Compliance\Services\AuditRecorder;
use App\Modules\Consults\Models\Consult;
use App\Modules\Consults\Models\TriageIntake;
use App\Modules\Consults\Services\ConsultService;
use App\Modules\Consults\Services\ConsultStateMachine;
use App\Modules\Doctors\Models\Doctor;
use App\Modules\Patients\Models\Patient;
use App\Modules\Scheduling\Models\Booking;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly ConsultStateMachine $stateMachine,
        private readonly ConsultService $consults,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function book(Patient $patient, Doctor $doctor, CarbonImmutable $startsAtUtc, ?string $complaint): Booking
    {
        if (! $doctor->canConsult()) {
            throw new DomainException('This doctor is not currently taking bookings.');
        }

        if ($startsAtUtc->gt(CarbonImmutable::now()->addDays((int) config('booking.horizon_days')))) {
            throw new DomainException('That date is beyond the booking window.');
        }

        return DB::transaction(function () use ($patient, $doctor, $startsAtUtc, $complaint) {
            // Serialise competing writes for this doctor+instant, then re-validate
            // inside the lock. Postgres adds a partial unique index as the backstop.
            Booking::where('doctor_id', $doctor->id)
                ->where('starts_at', $startsAtUtc)
                ->lockForUpdate()
                ->get();

            $slot = $this->availability->isBookable($doctor, $startsAtUtc);
            if ($slot === null) {
                throw new DomainException('That time is no longer available. Please pick another slot.');
            }

            $booking = Booking::create([
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'starts_at' => $slot['starts_at'],
                'ends_at' => $slot['ends_at'],
                'state' => Booking::STATE_CONFIRMED,
                'complaint' => $complaint,
            ]);

            $this->audit->record($booking, 'booking.confirmed', $patient->user_id);

            return $booking;
        });
    }

    public function cancel(Booking $booking, string $cancelledBy, int $actorId): Booking
    {
        if ($booking->state !== Booking::STATE_CONFIRMED) {
            throw new DomainException('Only confirmed bookings can be cancelled.');
        }

        $cutoff = (int) config('booking.cancel_cutoff_minutes');
        if ($cancelledBy === 'patient' && $booking->starts_at->subMinutes($cutoff)->isPast()) {
            throw new DomainException(
                __('Bookings can only be cancelled up to :hours hours before the appointment.', ['hours' => intdiv($cutoff, 60)])
            );
        }

        $booking->update([
            'state' => Booking::STATE_CANCELLED,
            'cancelled_by' => $cancelledBy,
        ]);

        $this->audit->record($booking, 'booking.cancelled', $actorId, ['by' => $cancelledBy]);

        return $booking;
    }

    /** Reschedule = cancel + rebook atomically, keeping the audit chain linked. */
    public function reschedule(Booking $booking, CarbonImmutable $newStartsAtUtc, int $actorId): Booking
    {
        return DB::transaction(function () use ($booking, $newStartsAtUtc, $actorId) {
            $this->cancel($booking, 'patient', $actorId);

            $replacement = $this->book(
                $booking->patient,
                $booking->doctor,
                $newStartsAtUtc,
                $booking->complaint,
            );

            $replacement->update(['rescheduled_from_id' => $booking->id]);
            $this->audit->record($replacement, 'booking.rescheduled', $actorId, ['from' => $booking->id]);

            return $replacement->refresh();
        });
    }

    /**
     * Doctor begins the booked consult: creates an assigned consult, links it,
     * and drops the patient's complaint into the thread. Bypasses the on-demand
     * queue — the booking IS the assignment.
     */
    public function begin(Booking $booking, Doctor $doctor): Consult
    {
        if ($booking->state !== Booking::STATE_CONFIRMED) {
            throw new DomainException('This booking is not active.');
        }

        if ($booking->doctor_id !== $doctor->id) {
            throw new DomainException('Only the booked doctor can begin this consult.');
        }

        $opens = $booking->starts_at->subMinutes((int) config('booking.begin_early_minutes'));
        $closes = $booking->ends_at->addMinutes((int) config('booking.begin_grace_minutes'));
        if (now()->lt($opens) || now()->gt($closes)) {
            throw new DomainException('This consult can only be started around its booked time.');
        }

        return DB::transaction(function () use ($booking, $doctor) {
            $intake = TriageIntake::create([
                'patient_id' => $booking->patient_id,
                'complaint' => $booking->complaint ?? __('Booked appointment'),
                'answers' => [],
                'red_flag' => false,
            ]);

            $consult = Consult::create([
                'patient_id' => $booking->patient_id,
                'doctor_id' => $doctor->id,
                'triage_intake_id' => $intake->id,
                'state' => Consult::STATE_REQUESTED,
            ]);

            foreach ([Consult::STATE_TRIAGED, Consult::STATE_QUEUED, Consult::STATE_ASSIGNED, Consult::STATE_IN_CONSULT] as $state) {
                $this->stateMachine->transition($consult, $state, $doctor->user_id);
            }

            $booking->update(['consult_id' => $consult->id, 'state' => Booking::STATE_COMPLETED]);

            $this->consults->systemMessage(
                $consult,
                __('Booked appointment with Dr :name has started.', ['name' => $doctor->user->name])
            );

            $this->audit->record($booking, 'booking.consult_started', $doctor->user_id, ['consult_id' => $consult->id]);

            return $consult->refresh();
        });
    }

    /** Sweep: confirmed bookings whose window fully passed with no consult. */
    public function markNoShows(): int
    {
        $grace = (int) config('booking.begin_grace_minutes');
        $count = 0;

        Booking::where('state', Booking::STATE_CONFIRMED)
            ->where('ends_at', '<', now()->subMinutes($grace))
            ->each(function (Booking $booking) use (&$count) {
                $booking->update(['state' => Booking::STATE_NO_SHOW]);
                $this->audit->record($booking, 'booking.no_show');
                $count++;
            });

        return $count;
    }
}
