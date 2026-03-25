<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OkrGoal;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProjectTaskController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $tasks = $project->tasks()->get();

        return response()->json([
            'data' => $tasks->map(fn (Task $t) => $this->taskPayload($t)),
        ]);
    }

    public function show(Request $request, Project $project, Task $task): JsonResponse
    {
        Gate::authorize('view', $project);

        if ($task->project_id !== $project->id) {
            abort(404);
        }

        return response()->json([
            'data' => $this->taskPayload($task),
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'string', Rule::in(Task::KANBAN_STATUSES)],
            'version_id' => ['nullable', 'integer', Rule::exists('roadmap_versions', 'id')->where('project_id', $project->id)],
            'theme_id' => ['nullable', 'integer', Rule::exists('roadmap_themes', 'id')->where('project_id', $project->id)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['sometimes', 'integer', Rule::in([Task::PRIORITY_LOW, Task::PRIORITY_NORMAL, Task::PRIORITY_HIGH])],
            'due_date' => ['nullable', 'date'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'planning_status' => ['nullable', 'string', Rule::in(Task::PLANNING_STATUSES)],
            'okr_objective_id' => [
                'nullable',
                'integer',
                Rule::exists('okr_objectives', 'id')->whereIn(
                    'okr_goal_id',
                    OkrGoal::query()->where('project_id', $project->id)->pluck('id')
                ),
            ],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
        ]);

        $status = $validated['status'] ?? 'todo';
        $max = (int) $project->tasks()->where('status', $status)->max('position');

        $task = $project->tasks()->create([
            'title' => $validated['title'],
            'body' => $validated['body'] ?? null,
            'status' => $status,
            'position' => $max + 1,
            'version_id' => $validated['version_id'] ?? null,
            'theme_id' => $validated['theme_id'] ?? null,
            'assigned_to' => $validated['assigned_to'] ?? null,
            'priority' => $validated['priority'] ?? Task::PRIORITY_NORMAL,
            'due_date' => $validated['due_date'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'planning_status' => $validated['planning_status'] ?? null,
            'okr_objective_id' => $validated['okr_objective_id'] ?? null,
            'tags' => $validated['tags'] ?? null,
        ]);

        return response()->json([
            'data' => $this->taskPayload($task),
        ], 201);
    }

    public function update(Request $request, Project $project, Task $task): JsonResponse
    {
        Gate::authorize('update', $project);

        if ($task->project_id !== $project->id) {
            abort(404);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'string', Rule::in(Task::KANBAN_STATUSES)],
            'version_id' => ['nullable', 'integer', Rule::exists('roadmap_versions', 'id')->where('project_id', $project->id)],
            'theme_id' => ['nullable', 'integer', Rule::exists('roadmap_themes', 'id')->where('project_id', $project->id)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['sometimes', 'integer', Rule::in([Task::PRIORITY_LOW, Task::PRIORITY_NORMAL, Task::PRIORITY_HIGH])],
            'due_date' => ['nullable', 'date'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'planning_status' => ['nullable', 'string', Rule::in(Task::PLANNING_STATUSES)],
            'okr_objective_id' => [
                'nullable',
                'integer',
                Rule::exists('okr_objectives', 'id')->whereIn(
                    'okr_goal_id',
                    OkrGoal::query()->where('project_id', $project->id)->pluck('id')
                ),
            ],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ]);

        $task->update($validated);

        return response()->json([
            'data' => $this->taskPayload($task),
        ]);
    }

    public function destroy(Request $request, Project $project, Task $task): JsonResponse
    {
        Gate::authorize('update', $project);

        if ($task->project_id !== $project->id) {
            abort(404);
        }

        $task->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function taskPayload(Task $task): array
    {
        return [
            'id' => $task->id,
            'project_id' => $task->project_id,
            'version_id' => $task->version_id,
            'theme_id' => $task->theme_id,
            'okr_objective_id' => $task->okr_objective_id,
            'assigned_to' => $task->assigned_to,
            'title' => $task->title,
            'body' => $task->body,
            'status' => $task->status,
            'position' => $task->position,
            'priority' => $task->priority,
            'due_date' => $task->due_date?->format('Y-m-d'),
            'starts_at' => $task->starts_at?->format('Y-m-d'),
            'ends_at' => $task->ends_at?->format('Y-m-d'),
            'planning_status' => $task->planning_status,
            'tags' => $task->tags,
            'created_at' => $task->created_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
        ];
    }
}
