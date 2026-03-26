<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mcp;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Optional GET + Server-Sent Events channel for Streamable HTTP (MCP 2025-11-25).
 *
 * Laravel MCP's registrar maps GET /mcp/* to 405; some clients (e.g. Cursor) treat a 405
 * on their SSE probe as a hard failure. The spec allows GET to return {@code text/event-stream}
 * instead — we emit a short priming SSE (id + retry) and close; clients may reconnect.
 *
 * @see https://modelcontextprotocol.io/specification/2025-11-25/basic/transports#listening-for-messages-from-the-server
 */
final class WaypostMcpGetSseController
{
    public function __invoke(Request $request): Response|StreamedResponse
    {
        if (! config('waypost.mcp_enabled', true)) {
            return response()->json([
                'message' => 'The Waypost MCP HTTP server is disabled on this instance (WAYPOST_MCP_ENABLED=false).',
                'mcp_enabled' => false,
            ], 503);
        }

        $accept = (string) $request->header('Accept', '');
        if (! str_contains($accept, 'text/event-stream')) {
            return response('', 405)->header('Allow', 'POST, GET, OPTIONS');
        }

        return response()->stream(function (): void {
            $id = Str::uuid()->toString();
            echo 'id: '.$id."\n";
            echo 'retry: 60000'."\n";
            echo "\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
