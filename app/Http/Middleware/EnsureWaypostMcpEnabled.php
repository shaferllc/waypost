<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureWaypostMcpEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('waypost.mcp_enabled', true)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'The Waypost MCP HTTP server is disabled on this instance (WAYPOST_MCP_ENABLED=false).',
            'mcp_enabled' => false,
        ], 503);
    }
}
