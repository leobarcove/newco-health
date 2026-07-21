<?php

namespace App\Modules\Consults\Services;

use App\Modules\Compliance\Services\AuditRecorder;
use App\Modules\Consults\Models\Consult;
use DomainException;

/**
 * The consult state machine (dev plan §5.4). Every transition is audited.
 * Modality switches do NOT change state — the thread persists across
 * chat/voice/video (the low-bandwidth ladder).
 */
class ConsultStateMachine
{
    /** @var array<string, list<string>> allowed transitions */
    private const TRANSITIONS = [
        Consult::STATE_REQUESTED => [Consult::STATE_TRIAGED, Consult::STATE_ESCALATED, Consult::STATE_ABANDONED],
        Consult::STATE_TRIAGED => [Consult::STATE_QUEUED, Consult::STATE_ESCALATED, Consult::STATE_ABANDONED],
        Consult::STATE_QUEUED => [Consult::STATE_ASSIGNED, Consult::STATE_ESCALATED, Consult::STATE_ABANDONED],
        Consult::STATE_ASSIGNED => [Consult::STATE_IN_CONSULT, Consult::STATE_QUEUED, Consult::STATE_ESCALATED, Consult::STATE_ABANDONED],
        Consult::STATE_IN_CONSULT => [Consult::STATE_CONCLUDED, Consult::STATE_ESCALATED],
        Consult::STATE_CONCLUDED => [Consult::STATE_CLOSED],
        Consult::STATE_CLOSED => [],
        Consult::STATE_ESCALATED => [Consult::STATE_CLOSED],
        Consult::STATE_ABANDONED => [],
    ];

    public function __construct(private readonly AuditRecorder $audit)
    {
    }

    public function transition(Consult $consult, string $to, ?int $actorId = null): Consult
    {
        $from = $consult->state;

        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new DomainException("Invalid consult transition: {$from} → {$to}");
        }

        $consult->state = $to;

        match ($to) {
            Consult::STATE_QUEUED => $consult->queued_at = now(),
            Consult::STATE_ASSIGNED => $consult->assigned_at = now(),
            Consult::STATE_CONCLUDED => $consult->concluded_at = now(),
            default => null,
        };

        $consult->save();

        $this->audit->record($consult, "consult.{$from}_to_{$to}", $actorId);

        return $consult;
    }

    public function canTransition(Consult $consult, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$consult->state] ?? [], true);
    }
}
