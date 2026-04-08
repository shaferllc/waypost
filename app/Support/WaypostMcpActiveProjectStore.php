<?php

namespace App\Support;

use App\Models\PersonalAccessToken;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class WaypostMcpActiveProjectStore
{
    private const CACHE_TTL_SECONDS = 86400 * 365 * 5;

    public function cacheKey(): ?string
    {
        $user = Auth::user();
        if ($user === null) {
            return null;
        }

        $token = $user->currentAccessToken();
        if (! $token instanceof PersonalAccessToken || ! $token->exists) {
            return null;
        }

        return 'waypost.mcp.active_project.'.$token->id;
    }

    /**
     * Remember which project follow-up MCP tools should use when project_id is omitted.
     *
     * @throws InvalidArgumentException
     */
    public function remember(int $projectId): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $project = Project::query()->find($projectId);
        if ($project === null) {
            throw new InvalidArgumentException("Unknown project id {$projectId}.");
        }

        Gate::forUser($user)->authorize('view', $project);

        $key = $this->cacheKey();
        if ($key === null) {
            return;
        }

        Cache::put($key, $projectId, self::CACHE_TTL_SECONDS);
    }

    public function get(): ?int
    {
        $key = $this->cacheKey();
        if ($key === null) {
            return null;
        }

        $id = Cache::get($key);
        if ($id === null) {
            return null;
        }

        $id = (int) $id;

        return $id > 0 ? $id : null;
    }

    /**
     * Resolve project id: explicit argument wins, then remembered default, then project-scoped token home.
     *
     * @throws InvalidArgumentException
     */
    public function resolveProjectId(?int $explicit): int
    {
        if ($explicit !== null && $explicit > 0) {
            return $explicit;
        }

        $fromCache = $this->get();
        if ($fromCache !== null) {
            return $fromCache;
        }

        $user = Auth::user();
        if ($user !== null) {
            $token = $user->currentAccessToken();
            $scoped = EnforceProjectScopedSanctumToken::scopedProjectIdFromToken($token);
            if ($scoped !== null) {
                return $scoped;
            }
        }

        throw new InvalidArgumentException(
            'No project_id and no MCP default project. Create a project with waypost_create_project (which sets the default), call waypost_set_active_project, or pass project_id explicitly.',
        );
    }
}
