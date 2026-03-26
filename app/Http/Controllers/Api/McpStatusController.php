<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceProjectScopedSanctumToken;
use App\Support\WaypostCursorArtifacts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class McpStatusController extends Controller
{
    /**
     * Lightweight connectivity check for the Waypost HTTPS MCP endpoint and API clients.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        $scopedId = EnforceProjectScopedSanctumToken::scopedProjectIdFromToken($token);

        return response()->json([
            'app_name' => config('app.name'),
            'laravel_version' => app()->version(),
            'api_url' => WaypostCursorArtifacts::publicBaseUrl().'/api',
            'mcp_http_url' => WaypostCursorArtifacts::mcpHttpUrl(),
            'mcp_reachability_url' => WaypostCursorArtifacts::mcpReachabilityUrl(),
            'authenticated_user_id' => $user?->id,
            'token_project_scope' => $scopedId,
        ]);
    }
}
