<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-sent strings (system messages, SMS, errors) follow the user's chosen
 * locale. Supported: en (default), pcm (Nigerian Pidgin); Hausa in Phase 2.
 */
class SetUserLocale
{
    public const SUPPORTED = ['en', 'pcm', 'ha'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale;

        if (in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
