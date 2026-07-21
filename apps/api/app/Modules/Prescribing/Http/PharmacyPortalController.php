<?php

namespace App\Modules\Prescribing\Http;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Prescribing\Models\Pharmacy;
use App\Modules\Prescribing\Models\Prescription;
use App\Modules\Prescribing\Services\PrescribingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * The pharmacist's counter flow: sign in → enter the patient's pickup code →
 * verify the medicines → dispense. Deliberately minimal PHI: first name only,
 * no complaint, no thread access.
 */
class PharmacyPortalController extends Controller
{
    public function __construct(private readonly PrescribingService $prescribing)
    {
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->where('role', User::ROLE_PHARMACY)->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => __('Email or password is incorrect.')], 422);
        }

        $pharmacy = Pharmacy::find($user->pharmacy_id);
        if ($pharmacy === null || $pharmacy->status !== Pharmacy::STATUS_ACTIVE) {
            return response()->json(['message' => __('This pharmacy account is not active.')], 403);
        }

        return response()->json([
            'token' => $user->createToken('pharmacy-portal')->plainTextToken,
            'pharmacy' => ['id' => $pharmacy->id, 'name' => $pharmacy->name],
        ]);
    }

    public function lookup(Request $request, string $code): JsonResponse
    {
        $this->authorisePharmacist($request);

        $prescription = $this->prescribing->lookup($code);

        return response()->json($this->serialise($prescription));
    }

    public function dispense(Request $request): JsonResponse
    {
        $this->authorisePharmacist($request);

        $data = $request->validate(['pickup_code' => ['required', 'string', 'max:12']]);

        $prescription = $this->prescribing->dispense(
            $data['pickup_code'],
            $request->user()->id,
            $request->user()->pharmacy_id,
        );

        return response()->json($this->serialise($prescription));
    }

    private function authorisePharmacist(Request $request): void
    {
        abort_unless($request->user()->role === User::ROLE_PHARMACY, 403);
        abort_if($request->user()->pharmacy_id === null, 403);
    }

    private function serialise(Prescription $prescription): array
    {
        $prescription->loadMissing('items.formularyItem', 'patient.user', 'doctor.user');

        return [
            'pickup_code' => $prescription->pickup_code,
            'status' => $prescription->status,
            // First name only — enough to greet the patient, no more (PHI minimisation).
            'patient_first_name' => explode(' ', trim($prescription->patient->user->name))[0] ?: 'Patient',
            'doctor_name' => $prescription->doctor->user->name,
            'mdcn_licence_no' => $prescription->doctor->mdcn_licence_no,
            'issued_at' => $prescription->created_at->toIso8601String(),
            'dispensed_at' => $prescription->dispensed_at?->toIso8601String(),
            'items' => $prescription->items->map(fn ($item) => [
                'medicine' => $item->formularyItem->label(),
                'dosage' => $item->dosage,
                'duration_days' => $item->duration_days,
                'instructions' => $item->instructions,
            ]),
        ];
    }
}
