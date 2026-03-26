<?php

use App\Http\Controllers\Mcp\WaypostMcpGetSseController;
use App\Http\Middleware\EnsureWaypostMcpEnabled;
use App\Http\Middleware\LogWaypostMcpHttp;
use App\Http\Middleware\NormalizeMcpStreamableHttpAccept;
use App\Mcp\Servers\WaypostHttpServer;
use App\Support\WaypostCursorArtifacts;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::get('/mcp/waypost/reachable', static function () {
    $postUrl = WaypostCursorArtifacts::mcpHttpUrl();
    $enabled = (bool) config('waypost.mcp_enabled', true);

    if (! $enabled) {
        return response()->json(array_filter([
            'ok' => false,
            'mcp_enabled' => false,
            'mcp_post_url' => $postUrl,
            'message' => 'MCP HTTP is disabled (WAYPOST_MCP_ENABLED=false). Re-enable in .env and reload PHP to use this endpoint.',
        ], static fn (mixed $v): bool => $v !== null));
    }

    $message = str_starts_with($postUrl, 'https://')
        ? 'If this JSON loads in a browser but Cursor still fails to connect, the editor runtime may not trust your local HTTPS certificate; try http:// for the MCP URL on Herd/Valet or fix CA trust for Electron.'
        : 'MCP is configured over HTTP (no TLS). If Cursor still fails, enable the waypost server under Settings → MCP, use this exact mcp_post_url, and send Authorization: Bearer with your project API token.';

    return response()->json(array_filter([
        'ok' => true,
        'mcp_enabled' => true,
        'mcp_post_url' => $postUrl,
        'message' => $message,
        // Herd/Valet often 301 http→https before PHP runs; OPTIONS then gets HTML, no CORS → Cursor "Error POSTing".
        'http_redirect_warning' => str_starts_with($postUrl, 'http://')
            ? 'If your host redirects HTTP to HTTPS (common on Herd), MCP must use https://… in Cursor — not http:// — or preflight fails with no CORS headers.'
            : null,
    ], static fn (mixed $v): bool => $v !== null));
})->middleware('throttle:60,1');

Mcp::web('/mcp/waypost', WaypostHttpServer::class)
    ->middleware([
        EnsureWaypostMcpEnabled::class,
        NormalizeMcpStreamableHttpAccept::class,
        LogWaypostMcpHttp::class,
        'auth:sanctum',
        'throttle:mcp',
    ]);

// Must be registered after Mcp::web(): the route collection overwrites same method+URI, and
// the package registers GET → 405 first. Do not add NormalizeMcpStreamableHttpAccept here.
Route::get('/mcp/waypost', WaypostMcpGetSseController::class)->middleware([
    EnsureWaypostMcpEnabled::class,
    LogWaypostMcpHttp::class,
    'auth:sanctum',
    'throttle:mcp',
]);
