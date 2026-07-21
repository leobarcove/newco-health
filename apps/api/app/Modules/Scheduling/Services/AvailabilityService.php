<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Doctors\Models\Doctor;
use App\Modules\Scheduling\Models\AvailabilityException;
use App\Modules\Scheduling\Models\AvailabilityTemplate;
use App\Modules\Scheduling\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Generates bookable slots on demand from weekly templates + date exceptions.
 * Slots are never materialised into the database — the templates are the truth,
 * so schedule changes apply instantly and there is nothing to backfill.
 *
 * All returned instants are UTC; template/exception times are doctor-local.
 */
class AvailabilityService
{
    /**
     * @return Collection<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    public function slotsFor(Doctor $doctor, CarbonImmutable $localDate): Collection
    {
        $timezone = $doctor->timezone;
        $exceptions = AvailabilityException::where('doctor_id', $doctor->id)
            ->whereDate('date', $localDate->toDateString())
            ->get();

        $wholeDayOff = $exceptions->contains(
            fn (AvailabilityException $e) => $e->kind === AvailabilityException::KIND_UNAVAILABLE && $e->start_time === null
        );

        $windows = collect();

        if (! $wholeDayOff) {
            $windows = AvailabilityTemplate::where('doctor_id', $doctor->id)
                ->where('active', true)
                ->where('weekday', $localDate->dayOfWeekIso)
                ->get()
                ->map(fn (AvailabilityTemplate $t) => [
                    'start' => $t->start_time,
                    'end' => $t->end_time,
                    'slot_minutes' => $t->slot_minutes,
                ]);
        }

        // Ad-hoc extra hours apply even on an otherwise templated day.
        $windows = $windows->concat(
            $exceptions
                ->where('kind', AvailabilityException::KIND_EXTRA)
                ->map(fn (AvailabilityException $e) => [
                    'start' => $e->start_time,
                    'end' => $e->end_time,
                    'slot_minutes' => $e->slot_minutes,
                ])
        );

        $partialBlocks = $exceptions
            ->filter(fn (AvailabilityException $e) => $e->kind === AvailabilityException::KIND_UNAVAILABLE && $e->start_time !== null);

        $earliest = CarbonImmutable::now()->addMinutes((int) config('booking.min_lead_minutes'));

        $slots = collect();
        foreach ($windows as $window) {
            $cursor = $localDate->setTimezone($timezone)->setTimeFromTimeString($window['start']);
            $windowEnd = $localDate->setTimezone($timezone)->setTimeFromTimeString($window['end']);

            while ($cursor->addMinutes($window['slot_minutes'])->lte($windowEnd)) {
                $slotEnd = $cursor->addMinutes($window['slot_minutes']);

                $blocked = $partialBlocks->contains(function (AvailabilityException $block) use ($cursor, $slotEnd, $localDate, $timezone) {
                    $blockStart = $localDate->setTimezone($timezone)->setTimeFromTimeString($block->start_time);
                    $blockEnd = $localDate->setTimezone($timezone)->setTimeFromTimeString($block->end_time);

                    return $cursor->lt($blockEnd) && $slotEnd->gt($blockStart);
                });

                if (! $blocked && $cursor->utc()->gte($earliest)) {
                    $slots->push([
                        'starts_at' => $cursor->utc(),
                        'ends_at' => $slotEnd->utc(),
                    ]);
                }

                $cursor = $slotEnd;
            }
        }

        // Remove already-booked slots (pending payments hold the slot too —
        // they expire after 15 minutes if unpaid).
        $booked = Booking::where('doctor_id', $doctor->id)
            ->whereIn('state', Booking::SLOT_HOLDING_STATES)
            ->whereBetween('starts_at', [$localDate->startOfDay()->utc(), $localDate->endOfDay()->addDay()->utc()])
            ->pluck('starts_at')
            ->map(fn ($t) => $t->toIso8601String())
            ->all();

        return $slots
            ->reject(fn (array $slot) => in_array($slot['starts_at']->toIso8601String(), $booked, true))
            ->sortBy('starts_at')
            ->values();
    }

    /** Is this exact UTC instant an offered, unbooked slot for the doctor? */
    public function isBookable(Doctor $doctor, CarbonImmutable $startsAtUtc): ?array
    {
        $localDate = $startsAtUtc->setTimezone($doctor->timezone)->startOfDay();

        return $this->slotsFor($doctor, $localDate)
            ->first(fn (array $slot) => $slot['starts_at']->equalTo($startsAtUtc));
    }
}
