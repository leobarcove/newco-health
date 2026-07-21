<?php

namespace App\Modules\Patients\Http;

use App\Http\Controllers\Controller;
use App\Modules\Patients\Models\Sponsorship;
use App\Modules\Patients\Services\SponsorshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Patient side: seeing and answering sponsorship invitations. */
class SponsorshipController extends Controller
{
    public function __construct(private readonly SponsorshipService $sponsorships)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $patient = $request->user()->patient;
        abort_if($patient === null, 403);

        $items = Sponsorship::where('patient_id', $patient->id)
            ->with('sponsor')
            ->get()
            ->map(fn (Sponsorship $s) => [
                'id' => $s->id,
                'sponsor_name' => $s->sponsor->name,
                'status' => $s->status,
            ]);

        return response()->json($items);
    }

    public function respond(Request $request, Sponsorship $sponsorship): JsonResponse
    {
        abort_unless($request->user()->patient?->id === $sponsorship->patient_id, 403);

        $data = $request->validate(['accept' => ['required', 'boolean']]);

        $sponsorship = $this->sponsorships->respond($sponsorship, $data['accept']);

        return response()->json(['id' => $sponsorship->id, 'status' => $sponsorship->status]);
    }
}
