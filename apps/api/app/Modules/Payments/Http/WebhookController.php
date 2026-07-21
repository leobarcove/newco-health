<?php

namespace App\Modules\Payments\Http;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Services\PaymentService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(private readonly PaymentService $payments)
    {
    }

    /** Provider → us. Unauthenticated; trust is the HMAC signature only. */
    public function paystack(Request $request): JsonResponse
    {
        try {
            $handled = $this->payments->handleWebhook(
                $request->getContent(),
                $request->header('x-paystack-signature'),
            );
        } catch (DomainException) {
            return response()->json(['message' => 'invalid signature'], 401);
        }

        return response()->json(['handled' => $handled]);
    }
}
