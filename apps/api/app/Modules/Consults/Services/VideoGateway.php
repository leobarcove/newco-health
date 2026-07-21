<?php

namespace App\Modules\Consults\Services;

use App\Models\User;
use App\Modules\Consults\Models\Consult;

/**
 * The video-provider seam (dev plan §6, CLEA DailyCoController pattern).
 * Daily.co is primary; nothing outside this module may name a provider.
 */
interface VideoGateway
{
    public function name(): string;

    /** Create (or reuse) the consult's private room. Returns its URL. */
    public function ensureRoom(Consult $consult): string;

    /** Short-lived join token for one participant. */
    public function participantToken(Consult $consult, User $user, bool $isOwner): string;
}
