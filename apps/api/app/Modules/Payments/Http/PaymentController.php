<?php

namespace App\Modules\Payments\Http;

use App\Http\Controllers\Controller;
use App\Modules\Consults\Models\Consult;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments)
    {
    }

    /** Patient pays for their triaged consult. */
    public function payForConsult(Request $request, Consult $consult): JsonResponse
    {
        $this->authorize('view', $consult);
        abort_unless($request->user()->isPatient(), 403);

        $payment = $this->payments->payForConsult($consult);

        return response()->json($this->serialise($payment), 201);
    }

    public function show(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 403);

        return response()->json($this->serialise($payment));
    }

    private function serialise(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'status' => $payment->status,
            'amount_kobo' => $payment->amount_kobo,
            'amount_display' => '₦'.number_format($payment->amount_kobo / 100, 2),
            'currency' => $payment->currency,
            'reference' => $payment->reference,
            'checkout_url' => $payment->meta['checkout_url'] ?? null,
            'consult_id' => $payment->consult_id,
        ];
    }
}
