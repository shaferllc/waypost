<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\RoadmapTheme;
use App\Services\ChangelogRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectRoadmapThemeController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $themes = $project->themes()->get();

        return response()->json([
            'data' => $themes->map(fn (RoadmapTheme $t) => $this->themePayload($t)),
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:32'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ]);

        $max = (int) $project->themes()->max('sort_order');
        $validated['sort_order'] = $validated['sort_order'] ?? ($max + 1);

        $theme = $project->themes()->create($validated);

        app(ChangelogRecorder::class)->record(
            $request->user(),
            'roadmap_theme.created',
            "Theme: {$theme->name}",
            $project->id,
            ['roadmap_theme_id' => $theme->id],
            $request->header('X-Waypost-Source'),
        );

        return response()->json([
            'data' => $this->themePayload($theme),
        ], 201);
    }

    public function update(Request $request, Project $project, RoadmapTheme $theme): JsonResponse
    {
        Gate::authorize('update', $project);

        if ($theme->project_id !== $project->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:32'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ]);

        $theme->update($validated);

        app(ChangelogRecorder::class)->record(
            $request->user(),
            'roadmap_theme.updated',
            "Theme updated: {$theme->name}",
            $project->id,
            ['roadmap_theme_id' => $theme->id],
            $request->header('X-Waypost-Source'),
        );

        return response()->json([
            'data' => $this->themePayload($theme->fresh()),
        ]);
    }

    public function destroy(Request $request, Project $project, RoadmapTheme $theme): JsonResponse
    {
        Gate::authorize('update', $project);

        if ($theme->project_id !== $project->id) {
            abort(404);
        }

        $name = $theme->name;
        $tid = $theme->id;
        $theme->delete();

        app(ChangelogRecorder::class)->record(
            $request->user(),
            'roadmap_theme.deleted',
            "Theme deleted: {$name}",
            $project->id,
            ['roadmap_theme_id' => $tid],
            $request->header('X-Waypost-Source'),
        );

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function themePayload(RoadmapTheme $theme): array
    {
        return [
            'id' => $theme->id,
            'project_id' => $theme->project_id,
            'name' => $theme->name,
            'color' => $theme->color,
            'sort_order' => $theme->sort_order,
            'created_at' => $theme->created_at?->toIso8601String(),
            'updated_at' => $theme->updated_at?->toIso8601String(),
        ];
    }
}
