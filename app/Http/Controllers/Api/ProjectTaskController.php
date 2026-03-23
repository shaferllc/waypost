<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProjectTaskController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'string', Rule::in(Task::KANBAN_STATUSES)],
            'version_id' => ['nullable', 'integer', Rule::exists('roadmap_versions', 'id')->where('project_id', $project->id)],
        ]);

        $status = $validated['status'] ?? 'todo';
        $max = (int) $project->tasks()->where('status', $status)->max('position');

        $task = $project->tasks()->create([
            'title' => $validated['title'],
            'body' => $validated['body'] ?? null,
            'status' => $status,
            'position' => $max + 1,
            'version_id' => $validated['version_id'] ?? null,
        ]);

        return response()->json([
            'data' => [
                'id' => $task->id,
                'project_id' => $task->project_id,
                'version_id' => $task->version_id,
                'title' => $task->title,
                'body' => $task->body,
                'status' => $task->status,
                'position' => $task->position,
                'created_at' => $task->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
