<?php

use App\Http\Middleware\ApiAcceptsJson;
use App\Http\Middleware\EnforceProjectScopedSanctumToken;
use App\Http\Middleware\EnsureTwoFactorChallengeSession;
use Dply\FleetOperator\Http\Middleware\AuthenticateFleetOperator;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Honor X-Forwarded-* from the edge (e.g. dply.io) so scheme/host match the public HTTPS URL.
        $middleware->trustProxies(at: '*');

        $middleware->api(prepend: [
            ApiAcceptsJson::class,
        ]);
        $middleware->alias([
            'token.project' => EnforceProjectScopedSanctumToken::class,
            'fleet.operator' => AuthenticateFleetOperator::class,
            'two_factor.challenge' => EnsureTwoFactorChallengeSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
