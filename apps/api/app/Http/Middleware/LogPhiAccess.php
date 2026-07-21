<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every successful read of PHI: who, what, when, from where
 * (dev plan §12 — surfaced in the compliance console). Applied per-route:
 *   ->middleware('phi.log:consult.messages.read')
 */
class LogPhiAccess
{
    public function handle(Request $request, Closure $next, string $label): Response
    {
        $response = $next($request);

        if ($request->user() !== null && $response->getStatusCode() < 400) {
            $subject = collect($request->route()->parameters())->first(fn ($p) => $p instanceof Model);

            DB::table('phi_access_log')->insert([
                'user_id' => $request->user()->id,
                'label' => $label,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject !== null ? (string) $subject->getKey() : null,
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);
        }

        return $response;
    }
}
