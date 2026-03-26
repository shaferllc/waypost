<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Optional request/response logging for the Streamable HTTP MCP endpoint.
 * Cursor often surfaces only "Error POSTing to endpoint" with no detail; Laravel logs
 * plus {@see self::REQUEST_ID_HEADER} help prove whether the POST reached this app.
 */
final class LogWaypostMcpHttp
{
    public const REQUEST_ID_HEADER = 'X-Waypost-Mcp-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('waypost.mcp_log_requests')) {
            return $next($request);
        }

        $requestId = Str::uuid()->toString();
        $request->attributes->set('waypost_mcp_request_id', $requestId);

        $bearer = $request->bearerToken();
        $tokenFingerprint = $bearer !== null && $bearer !== ''
            ? substr(hash('sha256', $bearer), 0, 12)
            : null;

        try {
            /** @var Response $response */
            $response = $next($request);
        } catch (Throwable $e) {
            Log::info('waypost.mcp.http', [
                'request_id' => $requestId,
                'method' => $request->getMethod(),
                'path' => $request->path(),
                'status' => null,
                'exception' => $e::class,
                'ip' => $request->ip(),
                'token_fp' => $tokenFingerprint,
                'mcp_session_id' => $request->headers->get('MCP-Session-Id'),
            ]);

            throw $e;
        }

        $response->headers->set(self::REQUEST_ID_HEADER, $requestId);

        Log::info('waypost.mcp.http', [
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'ip' => $request->ip(),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'token_fp' => $tokenFingerprint,
            'mcp_session_id' => $request->headers->get('MCP-Session-Id'),
            'content_length' => $response->headers->get('Content-Length'),
        ]);

        return $response;
    }
}
