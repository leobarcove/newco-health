<?php

namespace App\Modules\Compliance\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AuditRecorder
{
    /**
     * Append an immutable audit event. Context must never contain PHI —
     * reference IDs only (CLAUDE.md rule 5).
     */
    public function record(Model $subject, string $event, ?int $actorId = null, array $context = []): void
    {
        DB::table('audit_events')->insert([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (string) $subject->getKey(),
            'event' => $event,
            'actor_id' => $actorId,
            'context' => json_encode($context),
            'created_at' => now(),
        ]);
    }
}
