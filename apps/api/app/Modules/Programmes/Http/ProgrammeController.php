<?php

namespace App\Modules\Programmes\Http;

use App\Http\Controllers\Controller;
use App\Modules\Programmes\Models\Programme;
use App\Modules\Programmes\Models\ProgrammeEnrolment;
use App\Modules\Programmes\Services\ProgrammeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgrammeController extends Controller
{
    public function __construct(private readonly ProgrammeService $programmes)
    {
    }

    /** Catalogue + the patient's enrolment state per programme. */
    public function index(Request $request): JsonResponse
    {
        $patient = $request->user()->patient;
        abort_if($patient === null, 403);

        $enrolments = ProgrammeEnrolment::where('patient_id', $patient->id)->get()->keyBy('programme_id');

        return response()->json(
            Programme::where('active', true)->get()->map(fn (Programme $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'monthly_display' => '₦'.number_format($p->monthly_price_kobo / 100),
                'check_in_every_days' => $p->check_in_every_days,
                'enrolment' => $enrolments->get($p->id) === null ? null : [
                    'id' => $enrolments[$p->id]->id,
                    'status' => $enrolments[$p->id]->status,
                    'renews_at' => $enrolments[$p->id]->current_period_ends_at->toIso8601String(),
                    'next_check_in_at' => $enrolments[$p->id]->next_check_in_at->toIso8601String(),
                ],
            ]),
        );
    }

    public function enrol(Request $request, Programme $programme): JsonResponse
    {
        $patient = $request->user()->patient;
        abort_if($patient === null, 403);

        $result = $this->programmes->enrol($patient, $programme);

        return response()->json([
            'status' => $result['enrolment']->status,
            'checkout_url' => $result['checkout_url'],
        ], 201);
    }

    public function cancel(Request $request, ProgrammeEnrolment $enrolment): JsonResponse
    {
        abort_unless($request->user()->patient?->id === $enrolment->patient_id, 403);

        $this->programmes->cancel($enrolment);

        return response()->json(['status' => ProgrammeEnrolment::STATUS_CANCELLED]);
    }
}
