<?php

namespace App\Modules\Consults\Http;

use App\Http\Controllers\Controller;
use App\Modules\Consults\Models\Consult;
use App\Modules\Consults\Services\ConsultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsultController extends Controller
{
    public function __construct(private readonly ConsultService $consults)
    {
    }

    /** Patient starts a consult from their triage intake. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'complaint' => ['required', 'string', 'max:2000'],
            'answers' => ['array'],
            'answers.*' => ['boolean'],
        ]);

        $patient = $request->user()->patient;
        abort_if($patient === null, 403, 'Only patients can start consults.');

        // NDPA/MDCN: explicit telemedicine consent before any clinical service.
        $consents = app(\App\Modules\Compliance\Services\ConsentLedger::class);
        if (! $consents->has($request->user(), \App\Modules\Compliance\Services\ConsentLedger::KIND_TELEMEDICINE_TERMS)) {
            return response()->json([
                'message' => __('Please review and accept the telemedicine terms before starting a consult.'),
                'code' => 'consent_required',
                'kind' => \App\Modules\Compliance\Services\ConsentLedger::KIND_TELEMEDICINE_TERMS,
            ], 428);
        }

        $consult = $this->consults->createFromIntake($patient, $data['complaint'], $data['answers'] ?? []);

        return response()->json($this->serialise($consult), 201);
    }

    public function show(Request $request, Consult $consult): JsonResponse
    {
        $this->authorize('view', $consult);

        return response()->json($this->serialise($consult));
    }

    public function index(Request $request): JsonResponse
    {
        $patient = $request->user()->patient;
        abort_if($patient === null, 403);

        $consults = Consult::where('patient_id', $patient->id)->latest()->limit(20)->get();

        return response()->json($consults->map(fn (Consult $c) => $this->serialise($c)));
    }

    private function serialise(Consult $consult): array
    {
        return [
            'id' => $consult->id,
            'state' => $consult->state,
            'modality' => $consult->modality,
            'queue_position' => $this->consults->queuePosition($consult),
            'doctor' => $consult->doctor?->user?->only(['name']),
            'created_at' => $consult->created_at?->toIso8601String(),
        ];
    }
}
