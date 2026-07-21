<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Consults\Models\Consult;

class ConsultPolicy
{
    /** Patients see their own consults; the assigned doctor sees theirs; staff see all. */
    public function view(User $user, Consult $consult): bool
    {
        return $this->participate($user, $consult) || $user->role === User::ROLE_STAFF;
    }

    /** Only the two participants may write into a consult thread. */
    public function participate(User $user, Consult $consult): bool
    {
        if ($user->isPatient()) {
            return $user->patient?->id === $consult->patient_id;
        }

        if ($user->isDoctor()) {
            return $consult->doctor_id !== null && $user->doctor?->id === $consult->doctor_id;
        }

        return false;
    }
}
