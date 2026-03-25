<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectLink;
use App\Services\ChangelogRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectLinkController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $links = $project->links()->get();

        return response()->json([
            'data' => $links->map(fn (ProjectLink $link) => $this->linkPayload($link)),
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        $title = $validated['title'] ?? parse_url($validated['url'], PHP_URL_HOST) ?: 'Link';

        $link = $project->links()->create([
            'title' => $title,
            'url' => $validated['url'],
        ]);

        app(ChangelogRecorder::class)->record(
            $request->user(),
            'project_link.created',
            "Link: {$link->title}",
            $project->id,
            ['project_link_id' => $link->id, 'url' => $link->url],
            $request->header('X-Waypost-Source'),
        );

        return response()->json([
            'data' => $this->linkPayload($link),
        ], 201);
    }

    public function update(Request $request, Project $project, ProjectLink $link): JsonResponse
    {
        Gate::authorize('update', $project);

        if ($link->project_id !== $project->id) {
            abort(404);
        }

        $validated = $request->validate([
            'url' => ['sometimes', 'url', 'max:2048'],
            'title' => ['sometimes', 'string', 'max:120'],
        ]);

        $link->update($validated);

        app(ChangelogRecorder::class)->record(
            $request->user(),
            'project_link.updated',
            "Link updated: {$link->title}",
            $project->id,
            ['project_link_id' => $link->id],
            $request->header('X-Waypost-Source'),
        );

        return response()->json([
            'data' => $this->linkPayload($link->fresh()),
        ]);
    }

    public function destroy(Request $request, Project $project, ProjectLink $link): JsonResponse
    {
        Gate::authorize('update', $project);

        if ($link->project_id !== $project->id) {
            abort(404);
        }

        $title = $link->title;
        $linkId = $link->id;
        $link->delete();

        app(ChangelogRecorder::class)->record(
            $request->user(),
            'project_link.deleted',
            "Link removed: {$title}",
            $project->id,
            ['project_link_id' => $linkId],
            $request->header('X-Waypost-Source'),
        );

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function linkPayload(ProjectLink $link): array
    {
        return [
            'id' => $link->id,
            'project_id' => $link->project_id,
            'title' => $link->title,
            'url' => $link->url,
            'created_at' => $link->created_at?->toIso8601String(),
            'updated_at' => $link->updated_at?->toIso8601String(),
        ];
    }
}
