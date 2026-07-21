<?php

namespace App\Modules\Consults\Http;

use App\Http\Controllers\Controller;
use App\Modules\Consults\Models\Consult;
use App\Modules\Consults\Services\ConsultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorConsultController extends Controller
{
    public function __construct(private readonly ConsultService $consults)
    {
    }

    /** The queue board: oldest first. */
    public function queue(Request $request): JsonResponse
    {
        abort_unless($request->user()->isDoctor(), 403);

        $queued = Consult::where('state', Consult::STATE_QUEUED)
            ->orderBy('queued_at')
            ->limit(50)
            ->with('patient.user', 'dependant')
            ->get();

        // Name + who-it's-for are visible pre-accept; the complaint (the
        // PHI-heavy part) stays behind acceptance.
        return response()->json($queued->map(fn (Consult $c) => [
            'id' => $c->id,
            'patient_name' => $c->patient?->user?->name ?: 'Patient',
            'for_dependant' => $c->dependant?->name,
            'queued_at' => $c->queued_at?->toIso8601String(),
            'waiting_minutes' => (int) abs(now()->diffInMinutes($c->queued_at)),
        ]));
    }

    public function accept(Request $request, Consult $consult): JsonResponse
    {
        $doctor = $request->user()->doctor;
        abort_if($doctor === null, 403);

        $consult = $this->consults->accept($doctor, $consult);

        return response()->json(['id' => $consult->id, 'state' => $consult->state]);
    }

    public function conclude(Request $request, Consult $consult): JsonResponse
    {
        $this->authorize('participate', $consult);
        abort_unless($request->user()->isDoctor(), 403);

        $consult = $this->consults->conclude($consult, $request->user()->id);

        // Credit the doctor's share of a paid consult (no-op when unpaid).
        app(\App\Modules\Payments\Services\PaymentService::class)
            ->creditDoctorForConcludedConsult($consult);

        return response()->json(['id' => $consult->id, 'state' => $consult->state]);
    }
}
