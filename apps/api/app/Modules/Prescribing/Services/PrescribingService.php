<?php

namespace App\Modules\Prescribing\Services;

use App\Modules\Compliance\Services\AuditRecorder;
use App\Modules\Consults\Models\Consult;
use App\Modules\Consults\Models\ConsultMessage;
use App\Modules\Doctors\Models\Doctor;
use App\Modules\Prescribing\Models\FormularyItem;
use App\Modules\Prescribing\Models\Prescription;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PrescribingService
{
    public function __construct(private readonly AuditRecorder $audit)
    {
    }

    /**
     * Issue a prescription during (or within the 72h window after) a consult.
     * Drops a prescription card into the thread — the thread is the canonical
     * record (design plan §4.1).
     *
     * @param  list<array{formulary_item_id: int, dosage: string, duration_days: int, instructions?: string}>  $items
     */
    public function issue(Doctor $doctor, Consult $consult, array $items): Prescription
    {
        if (! in_array($consult->state, [Consult::STATE_IN_CONSULT, Consult::STATE_CONCLUDED], true)) {
            throw new DomainException('Prescriptions can only be issued during or just after a consult.');
        }

        if ($consult->doctor_id !== $doctor->id) {
            throw new DomainException('Only the consulting doctor may prescribe.');
        }

        if (! $doctor->canConsult()) {
            throw new DomainException('Doctor is not eligible to prescribe (status or licence).');
        }

        return DB::transaction(function () use ($doctor, $consult, $items) {
            $prescription = Prescription::create([
                'consult_id' => $consult->id,
                'doctor_id' => $doctor->id,
                'patient_id' => $consult->patient_id,
                'status' => Prescription::STATUS_ISSUED,
                'pickup_code' => $this->generatePickupCode(),
            ]);

            foreach ($items as $item) {
                $formularyItem = FormularyItem::where('active', true)->findOrFail($item['formulary_item_id']);

                $prescription->items()->create([
                    'formulary_item_id' => $formularyItem->id,
                    'dosage' => $item['dosage'],
                    'duration_days' => $item['duration_days'],
                    'instructions' => $item['instructions'] ?? null,
                ]);
            }

            ConsultMessage::create([
                'consult_id' => $consult->id,
                'sender_id' => $doctor->user_id,
                'kind' => ConsultMessage::KIND_PRESCRIPTION,
                'body' => $prescription->id,
            ]);

            $this->audit->record($prescription, 'prescription.issued', $doctor->user_id, [
                'consult_id' => $consult->id,
                'item_count' => count($items),
            ]);

            return $prescription->load('items.formularyItem');
        });
    }

    /** Pharmacy-facing: mark dispensed against a valid pickup code. */
    public function dispense(string $pickupCode, ?int $actorId = null): Prescription
    {
        $prescription = Prescription::where('pickup_code', $pickupCode)
            ->where('status', Prescription::STATUS_ISSUED)
            ->firstOrFail();

        $prescription->update([
            'status' => Prescription::STATUS_DISPENSED,
            'dispensed_at' => now(),
        ]);

        $this->audit->record($prescription, 'prescription.dispensed', $actorId);

        return $prescription;
    }

    /** Unambiguous 8-char code: no 0/O/1/I lookalikes — read out over the counter. */
    private function generatePickupCode(): string
    {
        do {
            $code = 'RX-'.strtoupper(Str::password(8, letters: true, numbers: true, symbols: false));
            $code = strtr($code, ['0' => '2', 'O' => 'P', '1' => '3', 'I' => 'J', 'o' => 'p', 'i' => 'j', 'l' => 'k']);
        } while (Prescription::where('pickup_code', $code)->exists());

        return $code;
    }
}
