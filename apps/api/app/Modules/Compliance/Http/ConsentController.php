<?php

namespace App\Modules\Compliance\Http;

use App\Http\Controllers\Controller;
use App\Modules\Compliance\Services\ConsentLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConsentController extends Controller
{
    public function __construct(private readonly ConsentLedger $consents)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->consents->statusFor($request->user()));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(ConsentLedger::KINDS)],
            'granted' => ['required', 'boolean'],
        ]);

        if ($data['granted']) {
            $this->consents->grant($request->user(), $data['kind'], $request->ip(), [
                'locale' => $request->user()->locale,
            ]);
        } else {
            $this->consents->revoke($request->user(), $data['kind'], $request->ip());
        }

        return response()->json($this->consents->statusFor($request->user()), 201);
    }
}
