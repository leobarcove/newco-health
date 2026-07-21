<?php

namespace App\Modules\Identity\Http;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Services\OtpService;
use App\Modules\Patients\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otp)
    {
    }

    public function requestOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+234[0-9]{10}$/'],
        ]);

        if (! $this->otp->request($data['phone'])) {
            return response()->json([
                'message' => __('Too many code requests. Please wait a while and try again.'),
            ], 429);
        }

        return response()->json(['message' => __('We sent a code to :phone by SMS.', ['phone' => $data['phone']])]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+234[0-9]{10}$/'],
            'code' => ['required', 'digits:6'],
        ]);

        $user = $this->otp->verify($data['phone'], $data['code']);

        if ($user === null) {
            return response()->json([
                'message' => __("That code didn't match or has expired. Request a new one."),
            ], 422);
        }

        // Every patient-role user gets a patient record on first login.
        if ($user->isPatient()) {
            Patient::firstOrCreate(['user_id' => $user->id]);
        }

        return response()->json([
            'token' => $user->createToken('pwa')->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'role' => $user->role,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'role' => $user->role,
            'locale' => $user->locale,
        ]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'locale' => ['sometimes', \Illuminate\Validation\Rule::in(\App\Http\Middleware\SetUserLocale::SUPPORTED)],
        ]);

        $request->user()->update($data);

        return $this->me($request);
    }
}
