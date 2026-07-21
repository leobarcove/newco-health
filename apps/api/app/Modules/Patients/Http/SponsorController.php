<?php

namespace App\Modules\Patients\Http;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Patients\Services\SponsorshipService;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/** Sponsor side: registration, sign-in, dashboard, invites, wallet top-up. */
class SponsorController extends Controller
{
    public function __construct(
        private readonly SponsorshipService $sponsorships,
        private readonly PaymentService $payments,
    ) {
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => User::ROLE_SPONSOR,
        ]);

        return response()->json([
            'token' => $user->createToken('sponsor-portal')->plainTextToken,
            'user' => $user->only(['id', 'name', 'email', 'role']),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->where('role', User::ROLE_SPONSOR)->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => __('Email or password is incorrect.')], 422);
        }

        return response()->json([
            'token' => $user->createToken('sponsor-portal')->plainTextToken,
            'user' => $user->only(['id', 'name', 'email', 'role']),
        ]);
    }

    public function overview(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === User::ROLE_SPONSOR, 403);

        $overview = $this->sponsorships->overviewFor($request->user());

        return response()->json([
            ...$overview,
            'wallet_display' => '₦'.number_format($overview['wallet_balance_kobo'] / 100, 2),
        ]);
    }

    public function invite(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === User::ROLE_SPONSOR, 403);

        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+234[0-9]{10}$/'],
            'label' => ['required', 'string', 'max:60'],
        ]);

        $sponsorship = $this->sponsorships->invite($request->user(), $data['phone'], $data['label']);

        return response()->json(['id' => $sponsorship->id, 'status' => $sponsorship->status], 201);
    }

    public function topUp(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === User::ROLE_SPONSOR, 403);

        $data = $request->validate([
            'amount_kobo' => ['required', 'integer', 'min:100000', 'max:100000000'], // ₦1,000 – ₦1,000,000
        ]);

        $payment = $this->payments->topUpWallet($request->user(), $data['amount_kobo']);

        return response()->json([
            'id' => $payment->id,
            'status' => $payment->status,
            'checkout_url' => $payment->meta['checkout_url'] ?? null,
        ], 201);
    }
}
