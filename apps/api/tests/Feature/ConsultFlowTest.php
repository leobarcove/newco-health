<?php

use App\Modules\Consults\Models\Consult;
use App\Modules\Doctors\Models\Doctor;
use App\Modules\Patients\Models\Patient;

it('runs the golden path: intake → queue → accept → chat → conclude', function () {
    $patient = Patient::factory()->create();
    $doctor = Doctor::factory()->create();

    // Patient starts a consult
    $consultId = $this->actingAs($patient->user)
        ->postJson('/api/consults', [
            'complaint' => 'Fever and headache for two days',
            'answers' => ['fever' => true],
        ])
        ->assertCreated()
        ->assertJsonPath('state', Consult::STATE_QUEUED)
        ->assertJsonPath('queue_position', 1)
        ->json('id');

    // Doctor sees it on the queue and accepts
    $this->actingAs($doctor->user)->getJson('/api/doctor/queue')
        ->assertOk()
        ->assertJsonPath('0.id', $consultId);

    $this->actingAs($doctor->user)
        ->postJson("/api/doctor/consults/{$consultId}/accept")
        ->assertOk()
        ->assertJsonPath('state', Consult::STATE_IN_CONSULT);

    // Both sides exchange messages
    $this->actingAs($patient->user)
        ->postJson("/api/consults/{$consultId}/messages", ['body' => 'It started on Monday.'])
        ->assertCreated();

    $this->actingAs($doctor->user)
        ->postJson("/api/consults/{$consultId}/messages", ['body' => 'Any vomiting?'])
        ->assertCreated();

    $messages = $this->actingAs($patient->user)
        ->getJson("/api/consults/{$consultId}/messages")
        ->assertOk()
        ->json();

    // 2 system messages (queued + doctor joined) + 2 human messages
    expect(collect($messages)->pluck('kind')->countBy()->toArray())
        ->toMatchArray(['system' => 2, 'text' => 2]);

    // Doctor concludes
    $this->actingAs($doctor->user)
        ->postJson("/api/doctor/consults/{$consultId}/conclude")
        ->assertOk()
        ->assertJsonPath('state', Consult::STATE_CONCLUDED);
});

it('escalates red-flag intakes immediately and never queues them', function () {
    $patient = Patient::factory()->create();

    $this->actingAs($patient->user)
        ->postJson('/api/consults', [
            'complaint' => 'Crushing chest pain',
            'answers' => ['chest_pain' => true],
        ])
        ->assertCreated()
        ->assertJsonPath('state', Consult::STATE_ESCALATED)
        ->assertJsonPath('queue_position', null);
});

it('blocks strangers from another patient\'s consult thread', function () {
    $patient = Patient::factory()->create();
    $stranger = Patient::factory()->create();

    $consultId = $this->actingAs($patient->user)
        ->postJson('/api/consults', ['complaint' => 'Malaria symptoms'])
        ->json('id');

    $this->actingAs($stranger->user)
        ->getJson("/api/consults/{$consultId}/messages")
        ->assertForbidden();

    $this->actingAs($stranger->user)
        ->postJson("/api/consults/{$consultId}/messages", ['body' => 'hello'])
        ->assertForbidden();
});

it('refuses acceptance by a doctor with an expired licence', function () {
    $patient = Patient::factory()->create();
    $doctor = Doctor::factory()->expiredLicence()->create();

    $consultId = $this->actingAs($patient->user)
        ->postJson('/api/consults', ['complaint' => 'Cough'])
        ->json('id');

    $this->actingAs($doctor->user)
        ->postJson("/api/doctor/consults/{$consultId}/accept")
        ->assertStatus(422); // DomainException → 422 with the doctor-facing message

    expect(Consult::find($consultId)->state)->toBe(Consult::STATE_QUEUED);
});

it('orders the queue by waiting time', function () {
    $first = Consult::factory()->queued()->state(['queued_at' => now()->subMinutes(30)])->create();
    $second = Consult::factory()->queued()->state(['queued_at' => now()->subMinutes(5)])->create();
    $doctor = Doctor::factory()->create();

    $this->actingAs($doctor->user)->getJson('/api/doctor/queue')
        ->assertJsonPath('0.id', $first->id)
        ->assertJsonPath('1.id', $second->id);
});
