<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceProjectScopedSanctumToken;
use App\Models\ChangelogEntry;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChangelogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 40), 1), 100);
        $projectId = $request->query('project_id');
        $user = $request->user();
        $scopedId = EnforceProjectScopedSanctumToken::scopedProjectIdFromToken($user->currentAccessToken());

        if ($scopedId !== null) {
            if (! Project::query()->accessible($user)->whereKey($scopedId)->exists()) {
                abort(403, 'This API token is limited to a project you cannot access.');
            }
            if ($projectId !== null && $projectId !== '' && (int) $projectId !== $scopedId) {
                abort(403, 'This API token cannot filter changelog by another project.');
            }

            $query = ChangelogEntry::query()
                ->where('project_id', $scopedId)
                ->latest();
        } else {
            $accessibleIds = Project::query()
                ->accessible($user)
                ->pluck('id');

            $query = ChangelogEntry::query()
                ->where(function ($q) use ($user, $accessibleIds): void {
                    $q->where('user_id', $user->id);
                    if ($accessibleIds->isNotEmpty()) {
                        $q->orWhereIn('project_id', $accessibleIds);
                    }
                })
                ->latest();

            if ($projectId !== null && $projectId !== '') {
                $query->where('project_id', (int) $projectId);
            }
        }

        $entries = $query->limit($limit)->get([
            'id',
            'project_id',
            'source',
            'action',
            'summary',
            'meta',
            'created_at',
        ]);

        return response()->json([
            'data' => $entries->map(fn (ChangelogEntry $e) => [
                'id' => $e->id,
                'project_id' => $e->project_id,
                'source' => $e->source,
                'action' => $e->action,
                'summary' => $e->summary,
                'meta' => $e->meta,
                'created_at' => $e->created_at?->toIso8601String(),
            ]),
        ]);
    }
}
