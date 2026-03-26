<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chromium Private Network Access (PNA) may send an OPTIONS request that only carries
 * Access-Control-Request-Private-Network and does not include
 * Access-Control-Request-Method. Laravel's HandleCors treats
 * the latter as required for a CORS preflight, so those OPTIONS requests fall through
 * to routing and return 405 — the real POST never runs and clients show a generic
 * "Error POSTing to endpoint" with no body.
 *
 * @see https://developer.chrome.com/blog/private-network-access-preflight
 */
final class RespondToMcpPrivateNetworkPreflightOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->isMethod('OPTIONS')
            && $request->is('mcp/*')
            && $request->headers->has('Access-Control-Request-Private-Network')
            && ! $request->headers->has('Access-Control-Request-Method')
        ) {
            $response = response('', 204);

            $origins = config('cors.allowed_origins', ['*']);
            if (in_array('*', $origins, true)) {
                $response->headers->set('Access-Control-Allow-Origin', '*');
            } else {
                $origin = (string) $request->headers->get('Origin');
                if ($origin !== '' && in_array($origin, $origins, true)) {
                    $response->headers->set('Access-Control-Allow-Origin', $origin);
                    $response->headers->set('Vary', 'Origin');
                }
            }

            $response->headers->set('Access-Control-Allow-Private-Network', 'true');

            return $response;
        }

        return $next($request);
    }
}
