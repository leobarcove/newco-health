<?php

namespace App\Modules\Payouts\Http;

use App\Http\Controllers\Controller;
use App\Modules\Payouts\Models\DoctorEarning;
use App\Modules\Payouts\Services\EarningsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EarningsController extends Controller
{
    public function __construct(private readonly EarningsService $earnings)
    {
    }

    /** The doctor's retention screen (design plan §4.2): totals + recent consult fees. */
    public function index(Request $request): JsonResponse
    {
        $doctor = $request->user()->doctor;
        abort_if($doctor === null, 403);

        $summary = $this->earnings->summaryFor($doctor);

        return response()->json([
            'pending_kobo' => $summary['pending_kobo'],
            'paid_kobo' => $summary['paid_kobo'],
            'pending_display' => '₦'.number_format($summary['pending_kobo'] / 100, 2),
            'paid_display' => '₦'.number_format($summary['paid_kobo'] / 100, 2),
            'recent' => $summary['recent']->map(fn (DoctorEarning $e) => [
                'consult_id' => $e->consult_id,
                'amount_kobo' => $e->amount_kobo,
                'status' => $e->status,
                'earned_at' => $e->created_at->toIso8601String(),
            ]),
        ]);
    }
}
