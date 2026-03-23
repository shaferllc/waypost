<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceProjectScopedSanctumToken;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $scopedId = EnforceProjectScopedSanctumToken::scopedProjectIdFromToken($user->currentAccessToken());

        if ($scopedId !== null) {
            $project = Project::query()
                ->accessible($user)
                ->whereKey($scopedId)
                ->first(['id', 'name', 'description', 'url']);

            return response()->json([
                'data' => $project ? collect([$project]) : collect(),
            ]);
        }

        $projects = Project::query()
            ->accessible($user)
            ->notArchived()
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'url', 'user_id', 'archived_at']);

        return response()->json(['data' => $projects]);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $project->load([
            'versions' => fn ($query) => $query
                ->orderBy('sort_order')
                ->orderBy('target_date')
                ->orderBy('id')
                ->select(['id', 'project_id', 'name', 'target_date', 'sort_order']),
        ]);

        return response()->json([
            'data' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'url' => $project->url,
                'versions' => $project->versions->map(fn ($v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'target_date' => $v->target_date?->format('Y-m-d'),
                ]),
            ],
        ]);
    }
}
