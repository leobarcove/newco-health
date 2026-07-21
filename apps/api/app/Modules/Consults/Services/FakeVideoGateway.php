<?php

namespace App\Modules\Consults\Services;

use App\Models\User;
use App\Modules\Consults\Models\Consult;

/**
 * Local driver: the whole call-orchestration flow (rooms, tokens, modality
 * ladder, system messages) runs and tests without a Daily.co account —
 * the SPA shows a simulated call panel for fake rooms.
 */
class FakeVideoGateway implements VideoGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function ensureRoom(Consult $consult): string
    {
        return "https://video.fake.local/{$consult->id}";
    }

    public function participantToken(Consult $consult, User $user, bool $isOwner): string
    {
        return 'fake-token-'.$user->id.($isOwner ? '-owner' : '');
    }
}
