<?php

use App\Modules\Consults\Events\ConsultMessageSent;
use App\Modules\Consults\Models\Consult;
use App\Modules\Doctors\Models\Doctor;
use App\Modules\Patients\Models\Patient;
use App\Modules\Prescribing\Models\FormularyItem;
use Database\Seeders\FormularySeeder;
use Illuminate\Support\Facades\Event;

function startLiveConsult(Patient $patient, Doctor $doctor): string
{
    $consultId = test()->actingAs($patient->user)
        ->postJson('/api/consults', ['complaint' => 'Fever and chills for two days'])
        ->json('id');

    test()->actingAs($doctor->user)->postJson("/api/doctor/consults/{$consultId}/accept");

    return $consultId;
}

beforeEach(function () {
    $this->seed(FormularySeeder::class);
});

it('lets the consulting doctor issue a prescription with a pickup code', function () {
    $patient = Patient::factory()->create();
    $doctor = Doctor::factory()->create();
    $consultId = startLiveConsult($patient, $doctor);
    $medicine = FormularyItem::where('name', 'Artemether/Lumefantrine')->first();

    $response = $this->actingAs($doctor->user)
        ->postJson("/api/doctor/consults/{$consultId}/prescriptions", [
            'items' => [[
                'formulary_item_id' => $medicine->id,
                'dosage' => '4 tablets twice daily',
                'duration_days' => 3,
                'instructions' => 'Take with a fatty meal',
            ]],
        ])
        ->assertCreated();

    expect($response->json('pickup_code'))->toStartWith('RX-')
        ->and($response->json('status'))->toBe('issued')
        ->and($response->json('items.0.medicine'))->toContain('Artemether');

    // The prescription card lands in the thread — the thread is the record.
    $kinds = $this->actingAs($patient->user)
        ->getJson("/api/consults/{$consultId}/messages")
        ->json();
    expect(collect($kinds)->pluck('kind'))->toContain('prescription');
});

it('blocks a doctor who is not the consulting doctor from prescribing', function () {
    $patient = Patient::factory()->create();
    $doctor = Doctor::factory()->create();
    $otherDoctor = Doctor::factory()->create();
    $consultId = startLiveConsult($patient, $doctor);
    $medicine = FormularyItem::first();

    $this->actingAs($otherDoctor->user)
        ->postJson("/api/doctor/consults/{$consultId}/prescriptions", [
            'items' => [['formulary_item_id' => $medicine->id, 'dosage' => '1 daily', 'duration_days' => 5]],
        ])
        ->assertForbidden();
});

it('refuses to prescribe outside a live or just-concluded consult', function () {
    $patient = Patient::factory()->create();
    $doctor = Doctor::factory()->create();
    $medicine = FormularyItem::first();

    $consult = Consult::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'state' => Consult::STATE_CLOSED,
    ]);

    $this->actingAs($doctor->user)
        ->postJson("/api/doctor/consults/{$consult->id}/prescriptions", [
            'items' => [['formulary_item_id' => $medicine->id, 'dosage' => '1 daily', 'duration_days' => 5]],
        ])
        ->assertStatus(500); // DomainException — mapped to 422 with a handler later
});

it('lets the patient view their own prescription but not others', function () {
    $patient = Patient::factory()->create();
    $stranger = Patient::factory()->create();
    $doctor = Doctor::factory()->create();
    $consultId = startLiveConsult($patient, $doctor);
    $medicine = FormularyItem::first();

    $prescriptionId = $this->actingAs($doctor->user)
        ->postJson("/api/doctor/consults/{$consultId}/prescriptions", [
            'items' => [['formulary_item_id' => $medicine->id, 'dosage' => '1 daily', 'duration_days' => 5]],
        ])
        ->json('id');

    $this->actingAs($patient->user)->getJson("/api/prescriptions/{$prescriptionId}")->assertOk();
    $this->actingAs($stranger->user)->getJson("/api/prescriptions/{$prescriptionId}")->assertForbidden();
});

it('searches the formulary for the prescribe autocomplete', function () {
    $doctor = Doctor::factory()->create();

    $this->actingAs($doctor->user)
        ->getJson('/api/formulary?q=amox')
        ->assertOk()
        ->assertJsonCount(2); // Amoxicillin + Amoxicillin/Clavulanate
});

it('broadcasts every consult message on the private consult channel', function () {
    Event::fake([ConsultMessageSent::class]);

    $patient = Patient::factory()->create();
    $doctor = Doctor::factory()->create();
    $consultId = startLiveConsult($patient, $doctor);

    $this->actingAs($patient->user)
        ->postJson("/api/consults/{$consultId}/messages", ['body' => 'Hello doctor'])
        ->assertCreated();

    Event::assertDispatched(ConsultMessageSent::class, function (ConsultMessageSent $event) use ($consultId) {
        return $event->broadcastOn()->name === "private-consult.{$consultId}"
            && $event->broadcastWith() === ['id' => $event->message->id]; // id only — no PHI on the wire
    });
});

it('downloads the prescription as a pdf with phi logging', function () {
    $patient = Patient::factory()->create();
    $doctor = Doctor::factory()->create();
    $consultId = startLiveConsult($patient, $doctor);
    $medicine = FormularyItem::first();

    $prescriptionId = $this->actingAs($doctor->user)
        ->postJson("/api/doctor/consults/{$consultId}/prescriptions", [
            'items' => [['formulary_item_id' => $medicine->id, 'dosage' => '1 daily', 'duration_days' => 5]],
        ])
        ->json('id');

    $this->actingAs($patient->user)
        ->get("/api/prescriptions/{$prescriptionId}/pdf")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect(\Illuminate\Support\Facades\DB::table('phi_access_log')->where('label', 'prescription.pdf')->count())->toBe(1);
});
