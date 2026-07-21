<?php

namespace App\Modules\Identity\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Per-device session management (dev plan §12): list, revoke one, revoke others. */
class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $currentId = $request->user()->currentAccessToken()?->id;

        return response()->json(
            $request->user()->tokens()->latest('last_used_at')->get()->map(fn ($token) => [
                'id' => $token->id,
                'device' => $token->name,
                'current' => $token->id === $currentId,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'signed_in_at' => $token->created_at->toIso8601String(),
            ]),
        );
    }

    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $request->user()->tokens()->where('id', $tokenId)->delete();

        return response()->json(['revoked' => true]);
    }

    /** Sign out everywhere else — keeps only the current device. */
    public function destroyOthers(Request $request): JsonResponse
    {
        $currentId = $request->user()->currentAccessToken()?->id;
        $request->user()->tokens()->where('id', '!=', $currentId)->delete();

        return response()->json(['revoked_others' => true]);
    }
}
