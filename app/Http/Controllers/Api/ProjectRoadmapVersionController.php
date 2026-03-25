<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\RoadmapVersion;
use App\Services\ChangelogRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectRoadmapVersionController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $versions = $project->versions()->get();

        return response()->json([
            'data' => $versions->map(fn (RoadmapVersion $v) => $this->versionPayload($v)),
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'target_date' => ['nullable', 'date'],
            'released_at' => ['nullable', 'date'],
            'release_notes' => ['nullable', 'string', 'max:10000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ]);

        $max = (int) $project->versions()->max('sort_order');
        $validated['sort_order'] = $validated['sort_order'] ?? ($max + 1);

        $version = $project->versions()->create($validated);

        app(ChangelogRecorder::class)->record(
            $request->user(),
            'roadmap_version.created',
            "Version: {$version->name}",
            $project->id,
            ['roadmap_version_id' => $version->id],
            $request->header('X-Waypost-Source'),
        );

        return response()->json([
            'data' => $this->versionPayload($version),
        ], 201);
    }

    public function update(Request $request, Project $project, RoadmapVersion $version): JsonResponse
    {
        Gate::authorize('update', $project);

        if ($version->project_id !== $project->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'target_date' => ['nullable', 'date'],
            'released_at' => ['nullable', 'date'],
            'release_notes' => ['nullable', 'string', 'max:10000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ]);

        $version->update($validated);

        app(ChangelogRecorder::class)->record(
            $request->user(),
            'roadmap_version.updated',
            "Version updated: {$version->name}",
            $project->id,
            ['roadmap_version_id' => $version->id],
            $request->header('X-Waypost-Source'),
        );

        return response()->json([
            'data' => $this->versionPayload($version->fresh()),
        ]);
    }

    public function destroy(Request $request, Project $project, RoadmapVersion $version): JsonResponse
    {
        Gate::authorize('update', $project);

        if ($version->project_id !== $project->id) {
            abort(404);
        }

        $name = $version->name;
        $vid = $version->id;
        $version->delete();

        app(ChangelogRecorder::class)->record(
            $request->user(),
            'roadmap_version.deleted',
            "Version deleted: {$name}",
            $project->id,
            ['roadmap_version_id' => $vid],
            $request->header('X-Waypost-Source'),
        );

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function versionPayload(RoadmapVersion $version): array
    {
        return [
            'id' => $version->id,
            'project_id' => $version->project_id,
            'name' => $version->name,
            'description' => $version->description,
            'target_date' => $version->target_date?->format('Y-m-d'),
            'released_at' => $version->released_at?->format('Y-m-d'),
            'release_notes' => $version->release_notes,
            'sort_order' => $version->sort_order,
            'created_at' => $version->created_at?->toIso8601String(),
            'updated_at' => $version->updated_at?->toIso8601String(),
        ];
    }
}
