<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceProjectScopedSanctumToken;
use App\Models\Project;
use App\Services\ChangelogRecorder;
use App\Services\ProjectCursorTokenIssuer;
use App\Support\WaypostCursorArtifacts;
use App\Support\WaypostMcpInternalApi;
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

    public function store(Request $request): JsonResponse
    {
        $scoped = EnforceProjectScopedSanctumToken::scopedProjectIdFromToken($request->user()->currentAccessToken()) !== null;
        if ($scoped && ! WaypostMcpInternalApi::isInternalDispatch()) {
            abort(403, 'Project-scoped tokens cannot create projects.');
        }

        Gate::authorize('create', Project::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'url' => ['nullable', 'url', 'max:2048'],
            'issue_sync_token' => ['sometimes', 'boolean'],
        ]);

        $issueSyncToken = $request->boolean('issue_sync_token');

        $project = $request->user()->projects()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'url' => $validated['url'] ?? null,
        ]);

        app(ChangelogRecorder::class)->record(
            $request->user(),
            'project.created',
            "Project: {$project->name}",
            $project->id,
            ['project_id' => $project->id],
            $request->header('X-Waypost-Source'),
        );

        $payload = [
            'data' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'url' => $project->url,
                'user_id' => $project->user_id,
                'archived_at' => $project->archived_at?->toIso8601String(),
                'created_at' => $project->created_at?->toIso8601String(),
            ],
        ];

        if ($issueSyncToken) {
            $plain = app(ProjectCursorTokenIssuer::class)->issue($project, $request->user());
            WaypostCursorArtifacts::flashCursorSetupToken($project->id, $plain);
            $payload['sync_token'] = $plain;
            $payload['waypost_json'] = WaypostCursorArtifacts::manifestJson($project, true, $plain);
            $payload['cursor_mcp_install_url'] = WaypostCursorArtifacts::cursorMcpInstallUrl($plain);
            $payload['_bootstrap_hint'] = 'Write `waypost_json` to repo-root `waypost.json` (gitignored). Set Cursor MCP env `WAYPOST_API_TOKEN` to `sync_token`, or open `cursor_mcp_install_url` once. Remove `api_token` from committed files.';
        }

        return response()->json($payload, 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $project->load([
            'themes' => fn ($query) => $query
                ->orderBy('sort_order')
                ->orderBy('id')
                ->select(['id', 'project_id', 'name', 'color', 'sort_order']),
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
                'themes' => $project->themes->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'color' => $t->color,
                    'sort_order' => $t->sort_order,
                ]),
                'versions' => $project->versions->map(fn ($v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'target_date' => $v->target_date?->format('Y-m-d'),
                ]),
            ],
        ]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'url' => ['nullable', 'url', 'max:2048'],
            'archived_at' => ['nullable', 'date'],
        ]);

        $project->update($validated);

        app(ChangelogRecorder::class)->record(
            $request->user(),
            'project.updated',
            "Project updated: {$project->name}",
            $project->id,
            ['project_id' => $project->id, 'fields' => array_keys($validated)],
            $request->header('X-Waypost-Source'),
        );

        return response()->json([
            'data' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'url' => $project->url,
                'user_id' => $project->user_id,
                'archived_at' => $project->archived_at?->toIso8601String(),
                'updated_at' => $project->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('delete', $project);

        $name = $project->name;
        $id = $project->id;
        $project->delete();

        app(ChangelogRecorder::class)->record(
            $request->user(),
            'project.deleted',
            "Project deleted: {$name}",
            null,
            ['project_id' => $id],
            $request->header('X-Waypost-Source'),
        );

        return response()->json(null, 204);
    }
}
