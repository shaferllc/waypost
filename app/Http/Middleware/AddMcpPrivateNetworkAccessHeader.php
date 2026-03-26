<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chromium Private Network Access (PNA): cross-origin requests to local addresses
 * (e.g. http://waypost.test → 127.0.0.1) send OPTIONS with
 * Access-Control-Request-Private-Network: true. Without Access-Control-Allow-Private-Network: true
 * on the preflight (and often the follow-up) response, Electron/Cursor never sends the POST —
 * the client surfaces a generic "Error POSTing to endpoint" with no body.
 *
 * @see https://developer.chrome.com/blog/private-network-access-preflight
 */
final class AddMcpPrivateNetworkAccessHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->is('mcp/*')) {
            return $response;
        }

        if (! config('waypost.mcp_allow_private_network_access_header', true)) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Private-Network', 'true');

        return $response;
    }
}
