<?php

namespace App\Modules\Consults\Http;

use App\Http\Controllers\Controller;
use App\Modules\Consults\Models\Consult;
use App\Modules\Consults\Models\ConsultNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SOAP-lite clinical notes — doctor-only, never exposed to patients or
 * sponsors. One note per consult, upserted as the doctor types.
 */
class NoteController extends Controller
{
    public function show(Request $request, Consult $consult): JsonResponse
    {
        $this->authorizeDoctor($request, $consult);

        $note = ConsultNote::where('consult_id', $consult->id)->first();

        return response()->json($this->serialise($note));
    }

    public function upsert(Request $request, Consult $consult): JsonResponse
    {
        $this->authorizeDoctor($request, $consult);

        $data = $request->validate([
            'subjective' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'objective' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'assessment' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'plan' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $note = ConsultNote::updateOrCreate(
            ['consult_id' => $consult->id],
            [...$data, 'doctor_id' => $request->user()->doctor->id],
        );

        return response()->json($this->serialise($note));
    }

    private function authorizeDoctor(Request $request, Consult $consult): void
    {
        abort_unless($request->user()->isDoctor(), 403);
        $this->authorize('participate', $consult);
    }

    private function serialise(?ConsultNote $note): array
    {
        return [
            'subjective' => $note?->subjective,
            'objective' => $note?->objective,
            'assessment' => $note?->assessment,
            'plan' => $note?->plan,
            'updated_at' => $note?->updated_at?->toIso8601String(),
        ];
    }
}
