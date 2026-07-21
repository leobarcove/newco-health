<?php

use App\Modules\Consults\Models\Consult;
use App\Modules\Doctors\Models\Doctor;
use App\Modules\Patients\Models\Patient;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\FakeGateway;
use App\Modules\Payouts\Models\DoctorEarning;

beforeEach(function () {
    config(['pricing.payments_required' => true]);
});

it('holds a consult at triaged until payment, then queues it', function () {
    $patient = Patient::factory()->create();

    $consultId = $this->actingAs($patient->user)
        ->postJson('/api/consults', ['complaint' => 'Persistent cough for a week'])
        ->assertCreated()
        ->assertJsonPath('state', Consult::STATE_TRIAGED)
        ->assertJsonPath('queue_position', null)
        ->json('id');

    // Fake gateway settles synchronously → consult queues immediately.
    $this->actingAs($patient->user)
        ->postJson("/api/consults/{$consultId}/pay")
        ->assertCreated()
        ->assertJsonPath('status', Payment::STATUS_SUCCEEDED)
        ->assertJsonPath('amount_kobo', 250000);

    expect(Consult::find($consultId)->state)->toBe(Consult::STATE_QUEUED);
});

it('never gates red-flag emergencies behind payment', function () {
    $patient = Patient::factory()->create();

    $this->actingAs($patient->user)
        ->postJson('/api/consults', [
            'complaint' => 'Crushing chest pain',
            'answers' => ['chest_pain' => true],
        ])
        ->assertCreated()
        ->assertJsonPath('state', Consult::STATE_ESCALATED);
});

it('is idempotent: paying twice creates one payment and one queue transition', function () {
    $patient = Patient::factory()->create();

    $consultId = $this->actingAs($patient->user)
        ->postJson('/api/consults', ['complaint' => 'Rash on both arms'])
        ->json('id');

    $first = $this->actingAs($patient->user)->postJson("/api/consults/{$consultId}/pay")->json('reference');
    $this->actingAs($patient->user)->postJson("/api/consults/{$consultId}/pay")->assertStatus(422); // already queued — not payable again

    expect(Payment::where('consult_id', $consultId)->count())->toBe(1)
        ->and(Payment::where('reference', $first)->first()->status)->toBe(Payment::STATUS_SUCCEEDED);
});

it('rejects webhooks with a bad signature and accepts signed ones idempotently', function () {
    $patient = Patient::factory()->create();
    $consultId = $this->actingAs($patient->user)
        ->postJson('/api/consults', ['complaint' => 'Sore throat'])
        ->json('id');

    // Create a pending payment without settling (bypass the fake auto-settle).
    $payment = Payment::create([
        'user_id' => $patient->user_id,
        'purpose' => Payment::PURPOSE_CONSULT,
        'consult_id' => $consultId,
        'amount_kobo' => 250000,
        'currency' => 'NGN',
        'gateway' => 'fake',
        'reference' => 'PAY-TESTREF123',
        'status' => Payment::STATUS_PENDING,
    ]);

    $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'PAY-TESTREF123']]);

    $this->postJson('/api/webhooks/paystack', json_decode($body, true), ['x-paystack-signature' => 'wrong'])
        ->assertStatus(401);
    expect($payment->refresh()->status)->toBe(Payment::STATUS_PENDING);

    $signature = hash_hmac('sha512', $body, FakeGateway::WEBHOOK_SECRET);
    $this->call('POST', '/api/webhooks/paystack', [], [], [], ['HTTP_X_PAYSTACK_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $body)
        ->assertOk();

    expect($payment->refresh()->status)->toBe(Payment::STATUS_SUCCEEDED)
        ->and(Consult::find($consultId)->state)->toBe(Consult::STATE_QUEUED);

    // Replay: same webhook again must change nothing (already settled).
    $this->call('POST', '/api/webhooks/paystack', [], [], [], ['HTTP_X_PAYSTACK_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $body)
        ->assertOk();
    expect(Payment::where('reference', 'PAY-TESTREF123')->count())->toBe(1);
});

it('credits the doctor 65% exactly once when a paid consult concludes', function () {
    $patient = Patient::factory()->create();
    $doctor = Doctor::factory()->create();

    $consultId = $this->actingAs($patient->user)
        ->postJson('/api/consults', ['complaint' => 'Malaria symptoms'])
        ->json('id');
    $this->actingAs($patient->user)->postJson("/api/consults/{$consultId}/pay");

    $this->actingAs($doctor->user)->postJson("/api/doctor/consults/{$consultId}/accept")->assertOk();
    $this->actingAs($doctor->user)->postJson("/api/doctor/consults/{$consultId}/conclude")->assertOk();

    $earning = DoctorEarning::where('consult_id', $consultId)->first();
    expect($earning)->not->toBeNull()
        ->and($earning->amount_kobo)->toBe(162500) // 65% of ₦2,500
        ->and($earning->status)->toBe(DoctorEarning::STATUS_PENDING);

    // Earnings summary reflects it.
    $summary = $this->actingAs($doctor->user)->getJson('/api/doctor/earnings')->assertOk()->json();
    expect($summary['pending_kobo'])->toBe(162500)
        ->and($summary['recent'])->toHaveCount(1);
});

it('credits nothing for unpaid consults', function () {
    config(['pricing.payments_required' => false]); // free mode
    $patient = Patient::factory()->create();
    $doctor = Doctor::factory()->create();

    $consultId = $this->actingAs($patient->user)
        ->postJson('/api/consults', ['complaint' => 'Headache'])
        ->json('id');
    $this->actingAs($doctor->user)->postJson("/api/doctor/consults/{$consultId}/accept");
    $this->actingAs($doctor->user)->postJson("/api/doctor/consults/{$consultId}/conclude");

    expect(DoctorEarning::count())->toBe(0);
});

it('blocks strangers from paying for or viewing another patient\'s payment', function () {
    $patient = Patient::factory()->create();
    $stranger = Patient::factory()->create();

    $consultId = $this->actingAs($patient->user)
        ->postJson('/api/consults', ['complaint' => 'Back pain'])
        ->json('id');

    $this->actingAs($stranger->user)->postJson("/api/consults/{$consultId}/pay")->assertForbidden();

    $paymentId = $this->actingAs($patient->user)->postJson("/api/consults/{$consultId}/pay")->json('id');
    $this->actingAs($stranger->user)->getJson("/api/payments/{$paymentId}")->assertForbidden();
});
