<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Scheduling\Models\Booking;

class BookingPolicy
{
    /** The booking's patient manages it; staff may too (refunds/ops). */
    public function manage(User $user, Booking $booking): bool
    {
        if ($user->isPatient()) {
            return $user->patient?->id === $booking->patient_id;
        }

        return $user->role === User::ROLE_STAFF;
    }
}
