<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Modules/Scheduling/Console',
        __DIR__.'/../app/Modules/Prescribing/Console',
        __DIR__.'/../app/Modules/Consults/Console',
        __DIR__.'/../app/Modules/Payouts/Console',
        __DIR__.'/../app/Modules/Programmes/Console',
    ])
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        // The SPA authorises private channels with its Sanctum bearer token.
        ['prefix' => 'api', 'middleware' => ['auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->alias([
            'phi.log' => \App\Http\Middleware\LogPhiAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Domain rule violations are client errors with human-readable
        // messages ("slot no longer available"), never 500s.
        $exceptions->render(function (\DomainException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        });
    })->create();
