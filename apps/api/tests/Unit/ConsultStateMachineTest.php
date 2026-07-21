<?php

use App\Modules\Compliance\Services\AuditRecorder;
use App\Modules\Consults\Models\Consult;
use App\Modules\Consults\Services\ConsultStateMachine;
use Illuminate\Support\Facades\DB;

it('allows the documented forward path', function () {
    $machine = new ConsultStateMachine(new AuditRecorder());
    $consult = Consult::factory()->create();

    foreach ([
        Consult::STATE_TRIAGED,
        Consult::STATE_QUEUED,
        Consult::STATE_ASSIGNED,
        Consult::STATE_IN_CONSULT,
        Consult::STATE_CONCLUDED,
        Consult::STATE_CLOSED,
    ] as $state) {
        $machine->transition($consult, $state);
        expect($consult->state)->toBe($state);
    }
});

it('rejects illegal jumps', function () {
    $machine = new ConsultStateMachine(new AuditRecorder());
    $consult = Consult::factory()->create(); // state: requested

    $machine->transition($consult, Consult::STATE_CONCLUDED);
})->throws(DomainException::class);

it('rejects transitions out of terminal states', function () {
    $machine = new ConsultStateMachine(new AuditRecorder());
    $consult = Consult::factory()->create(['state' => Consult::STATE_CLOSED]);

    $machine->transition($consult, Consult::STATE_QUEUED);
})->throws(DomainException::class);

it('writes an audit event for every transition', function () {
    $machine = new ConsultStateMachine(new AuditRecorder());
    $consult = Consult::factory()->create();

    $machine->transition($consult, Consult::STATE_TRIAGED);

    expect(DB::table('audit_events')
        ->where('subject_id', $consult->id)
        ->where('event', 'consult.requested_to_triaged')
        ->exists())->toBeTrue();
});

it('escalation is reachable from every live state', function () {
    $machine = new ConsultStateMachine(new AuditRecorder());

    foreach ([
        Consult::STATE_REQUESTED,
        Consult::STATE_TRIAGED,
        Consult::STATE_QUEUED,
        Consult::STATE_ASSIGNED,
        Consult::STATE_IN_CONSULT,
    ] as $from) {
        $consult = Consult::factory()->create(['state' => $from]);
        expect($machine->canTransition($consult, Consult::STATE_ESCALATED))
            ->toBeTrue("escalation must be reachable from {$from}");
    }
});
