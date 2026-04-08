<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use App\Models\Project;
use App\Support\WaypostMcpInternalApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceProjectScopedSanctumToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $token = $user->currentAccessToken();
        $scopedProjectId = self::scopedProjectIdFromToken($token);
        if ($scopedProjectId === null) {
            return $next($request);
        }

        $routeProject = $request->route('project');
        if ($routeProject instanceof Project && (int) $routeProject->id !== $scopedProjectId) {
            if (WaypostMcpInternalApi::isInternalDispatch() && $request->user()->can('view', $routeProject)) {
                return $next($request);
            }
            abort(403, 'This API token is limited to a different project.');
        }

        return $next($request);
    }

    /**
     * Only real DB tokens with project_id scope (not Sanctum::actingAs mocks).
     */
    public static function scopedProjectIdFromToken(?object $token): ?int
    {
        if (! $token instanceof PersonalAccessToken || ! $token->exists) {
            return null;
        }

        $pid = $token->project_id;
        if ($pid === null) {
            return null;
        }

        $id = (int) $pid;

        return $id > 0 ? $id : null;
    }
}
