<?php

use App\Models\User;
use App\Modules\Consults\Models\Consult;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

/*
 * Consult threads are private: only the two participants (and staff) may
 * subscribe — same rule as the REST policy (dev plan §12, WebSocket auth).
 */
Broadcast::channel('consult.{consultId}', function (User $user, string $consultId) {
    $consult = Consult::find($consultId);

    return $consult !== null && Gate::forUser($user)->allows('view', $consult);
});
