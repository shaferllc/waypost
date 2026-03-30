<?php

use App\Http\Middleware\AddMcpPrivateNetworkAccessHeader;
use App\Http\Middleware\ApiAcceptsJson;
use App\Http\Middleware\EnforceProjectScopedSanctumToken;
use App\Http\Middleware\EnsureTwoFactorChallengeSession;
use App\Http\Middleware\RespondToMcpPrivateNetworkPreflightOnly;
use Dply\FleetOperator\Http\Middleware\AuthenticateFleetOperator;
use Fleet\IdpClient\Http\Middleware\EnsureFleetSiteRequiresTwoFactor;
use Fleet\IdpClient\Http\Middleware\EnsureSatelliteEmailIsVerified;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified as LaravelEnsureEmailIsVerified;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // PNA-only OPTIONS (no Access-Control-Request-Method) must not reach routing (405).
        // Prepend Respond… after AddMcp so array_unshift puts Respond first (runs outermost).
        $middleware->prepend(AddMcpPrivateNetworkAccessHeader::class);
        $middleware->prepend(RespondToMcpPrivateNetworkPreflightOnly::class);

        // Honor X-Forwarded-* from the edge (e.g. dply.io) so scheme/host match the public HTTPS URL.
        $middleware->trustProxies(at: '*');

        $middleware->api(prepend: [
            ApiAcceptsJson::class,
        ]);
        // Middleware::alias() replaces the whole map — register every alias in one call (do not call
        // FleetSatelliteWebMiddleware::register() after this or token.project / fleet.operator are lost).
        // Config is not bound yet here; keep in sync with config/waypost.php fleet_login_enabled.
        $fleetLogin = filter_var(
            env('WAYPOST_FLEET_LOGIN_ENABLED', false),
            FILTER_VALIDATE_BOOL
        );

        $middleware->alias([
            'token.project' => EnforceProjectScopedSanctumToken::class,
            'fleet.operator' => AuthenticateFleetOperator::class,
            'two_factor.challenge' => EnsureTwoFactorChallengeSession::class,
            'verified' => $fleetLogin
                ? EnsureSatelliteEmailIsVerified::class
                : LaravelEnsureEmailIsVerified::class,
        ]);

        if ($fleetLogin) {
            $middleware->appendToGroup('web', [
                EnsureFleetSiteRequiresTwoFactor::class,
            ]);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('mcp/*')) {
                return null;
            }

            // Match Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader (auth middleware throws
            // before that middleware can decorate 401 responses).
            $isOauth = app('router')->has('mcp.oauth.protected-resource');
            $wwwAuthenticate = $isOauth
                ? 'Bearer realm="mcp", resource_metadata="'.route('mcp.oauth.protected-resource', ['path' => $request->path()]).'"'
                : 'Bearer realm="mcp", error="invalid_token"';

            return response()->json(['message' => $e->getMessage()], 401)
                ->header('WWW-Authenticate', $wwwAuthenticate);
        });
    })->create();
