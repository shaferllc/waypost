<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streamable HTTP MCP clients must send Accept including both application/json and text/event-stream.
 * If only text/event-stream (or another non-JSON-first) value is sent, Laravel's {@see Request::wantsJson()}
 * is false, so auth failures redirect to the web login (302 HTML) instead of returning 401 JSON — remote
 * MCP clients then report a generic POST error.
 */
final class NormalizeMcpStreamableHttpAccept
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json, text/event-stream');

        return $next($request);
    }
}
