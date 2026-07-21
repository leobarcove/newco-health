<?php

namespace App\Modules\Compliance\Services;

use App\Models\User;
use App\Modules\Compliance\Services\AuditRecorder;
use App\Modules\Consults\Models\Consult;
use App\Modules\Patients\Models\Dependant;
use App\Modules\Payments\Models\Payment;
use App\Modules\Prescribing\Models\Prescription;
use App\Modules\Scheduling\Models\Booking;
use Illuminate\Support\Facades\DB;

/**
 * NDPA data-subject rights.
 *
 * Erasure design note: clinical records must be retained under Nigerian
 * medical-records obligations, so "delete my account" performs
 * ANONYMISATION — identity fields are irreversibly removed and access is
 * revoked, while the pseudonymised clinical record is retained under its
 * legal basis. This is stated in the privacy policy.
 */
class DataSubjectService
{
    public function __construct(private readonly AuditRecorder $audit)
    {
    }

    /** Everything we hold about the user, machine-readable (right of access). */
    public function export(User $user): array
    {
        $patient = $user->patient;

        $consults = $patient === null ? collect() : Consult::where('patient_id', $patient->id)
            ->with('messages', 'intake')
            ->get()
            ->map(fn (Consult $c) => [
                'id' => $c->id,
                'state' => $c->state,
                'complaint' => $c->intake?->complaint,
                'created_at' => $c->created_at->toIso8601String(),
                'messages' => $c->messages
                    ->filter(fn ($m) => in_array($m->kind, ['text', 'system', 'prescription'], true))
                    ->map(fn ($m) => [
                        'kind' => $m->kind,
                        'body' => $m->body,
                        'mine' => $m->sender_id === $user->id,
                        'at' => $m->created_at->toIso8601String(),
                    ])->values(),
            ]);

        $this->audit->record($user, 'data_subject.exported', $user->id);

        return [
            'generated_at' => now()->toIso8601String(),
            'profile' => $user->only(['name', 'phone', 'email', 'role', 'locale']),
            'consents' => DB::table('consents')->where('user_id', $user->id)
                ->get(['kind', 'action', 'created_at']),
            'dependants' => $patient === null ? [] : Dependant::where('patient_id', $patient->id)
                ->get()->map(fn ($d) => $d->only(['name', 'relationship', 'date_of_birth', 'sex'])),
            'consults' => $consults,
            'prescriptions' => $patient === null ? [] : Prescription::where('patient_id', $patient->id)
                ->with('items.formularyItem')
                ->get()
                ->map(fn (Prescription $p) => [
                    'pickup_code' => $p->pickup_code,
                    'status' => $p->status,
                    'items' => $p->items->map(fn ($i) => $i->formularyItem->label().' — '.$i->dosage),
                    'issued_at' => $p->created_at->toIso8601String(),
                ]),
            'bookings' => $patient === null ? [] : Booking::where('patient_id', $patient->id)
                ->get()->map(fn (Booking $b) => [
                    'starts_at' => $b->starts_at->toIso8601String(),
                    'state' => $b->state,
                ]),
            'payments' => Payment::where('user_id', $user->id)
                ->get()->map(fn (Payment $p) => [
                    'reference' => $p->reference,
                    'purpose' => $p->purpose,
                    'amount_kobo' => $p->amount_kobo,
                    'status' => $p->status,
                    'at' => $p->created_at->toIso8601String(),
                ]),
        ];
    }

    /** Right to erasure — anonymise identity, revoke access, retain clinical record. */
    public function erase(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Identity gone, irreversibly.
            $user->update([
                'name' => 'Deleted User',
                'phone' => null,
                'email' => null,
                'erased_at' => now(),
            ]);

            // Every session and device is signed out for good.
            $user->tokens()->delete();

            // Dependant identities are the user's data too.
            if ($user->patient !== null) {
                Dependant::where('patient_id', $user->patient->id)
                    ->each(fn (Dependant $d) => $d->update(['name' => 'Redacted', 'date_of_birth' => null]));
                $user->patient->update(['medical_notes' => null]);
            }

            $this->audit->record($user, 'data_subject.erased', $user->id);
        });
    }
}
