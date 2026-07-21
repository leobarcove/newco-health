<?php

namespace App\Modules\Consults\Services;

use App\Models\User;
use App\Modules\Compliance\Services\AuditRecorder;
use App\Modules\Compliance\Services\FeatureFlags;
use App\Modules\Consults\Models\Consult;
use DomainException;

/**
 * The modality ladder's upper rungs (business plan §6): chat is the floor,
 * voice/video are upgrades on the SAME consult — never a new one. A dropped
 * call downgrades; the thread always survives.
 */
class VideoService
{
    public const FLAG = 'video_consults';

    public function __construct(
        private readonly VideoGateway $gateway,
        private readonly FeatureFlags $flags,
        private readonly ConsultService $consults,
        private readonly AuditRecorder $audit,
    ) {
    }

    /**
     * Start (or join) the consult's call. Idempotent — both participants call
     * this and land in the same room.
     *
     * @return array{provider: string, room_url: string, token: string, modality: string}
     */
    public function session(Consult $consult, User $user, string $modality): array
    {
        if (! $this->flags->enabled(self::FLAG)) {
            throw new DomainException('Voice and video calls are not enabled yet.');
        }

        if (! in_array($modality, ['voice', 'video'], true)) {
            throw new DomainException('Calls are voice or video.');
        }

        if ($consult->state !== Consult::STATE_IN_CONSULT) {
            throw new DomainException('Calls can only happen during a live consult.');
        }

        $isNewCall = $consult->daily_room === null;
        $roomUrl = $consult->daily_room ?? $this->gateway->ensureRoom($consult);

        if ($consult->daily_room !== $roomUrl || $consult->modality !== $modality) {
            $consult->update(['daily_room' => $roomUrl, 'modality' => $modality]);
        }

        if ($isNewCall) {
            $this->consults->systemMessage($consult, $modality === 'video'
                ? __('Video call started — tap the camera button to join.')
                : __('Voice call started — tap the phone button to join.'));
            $this->audit->record($consult, 'consult.call_started', $user->id, ['modality' => $modality]);
        }

        return [
            'provider' => $this->gateway->name(),
            'room_url' => $roomUrl,
            'token' => $this->gateway->participantToken($consult, $user, $user->isDoctor()),
            'modality' => $consult->refresh()->modality,
        ];
    }

    /** Step back down the ladder — the thread carries on, the room is kept for re-upgrade. */
    public function downgradeToChat(Consult $consult, User $user): Consult
    {
        if ($consult->modality === 'chat') {
            return $consult;
        }

        $consult->update(['modality' => 'chat']);
        $this->consults->systemMessage($consult, __('Back to chat — the conversation continues here.'));
        $this->audit->record($consult, 'consult.call_ended', $user->id);

        return $consult->refresh();
    }
}
