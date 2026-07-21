<?php

namespace App\Modules\Patients\Http;

use App\Http\Controllers\Controller;
use App\Modules\Patients\Models\Dependant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DependantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $patient = $request->user()->patient;
        abort_if($patient === null, 403);

        return response()->json(
            Dependant::where('patient_id', $patient->id)->get()->map(fn (Dependant $d) => $this->serialise($d)),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $patient = $request->user()->patient;
        abort_if($patient === null, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'relationship' => ['required', Rule::in(Dependant::RELATIONSHIPS)],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'sex' => ['sometimes', 'nullable', 'in:female,male'],
        ]);

        $dependant = Dependant::create([...$data, 'patient_id' => $patient->id]);

        return response()->json($this->serialise($dependant), 201);
    }

    public function destroy(Request $request, Dependant $dependant): JsonResponse
    {
        abort_unless($request->user()->patient?->id === $dependant->patient_id, 403);

        $dependant->delete();

        return response()->json(['deleted' => true]);
    }

    private function serialise(Dependant $dependant): array
    {
        return [
            'id' => $dependant->id,
            'name' => $dependant->name,
            'relationship' => $dependant->relationship,
            'date_of_birth' => $dependant->date_of_birth?->toDateString(),
            'sex' => $dependant->sex,
        ];
    }
}
