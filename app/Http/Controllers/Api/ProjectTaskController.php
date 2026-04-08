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

        $validated = $request->validate([
            'exclude_status' => ['nullable', 'string', 'max:500'],
            'open_only' => ['sometimes', 'boolean'],
            'version_id' => ['nullable', 'integer'],
            'theme_id' => ['nullable', 'integer'],
            'planning_status' => ['nullable', 'string', Rule::in(Task::PLANNING_STATUSES)],
        ]);

        $query = $project->tasks()->getQuery();

        if ($request->boolean('open_only')) {
            $query->where('status', '!=', 'done');
        }

        if (! empty($validated['exclude_status'])) {
            $exclude = $this->parseStatusFilter((string) $validated['exclude_status']);
            if ($exclude !== []) {
                $query->whereNotIn('status', $exclude);
            }
        }

        $statusInput = $request->input('status');
        if ($statusInput !== null && $statusInput !== '' && $statusInput !== []) {
            $include = $this->parseStatusFilterFromInput(is_array($statusInput) ? $statusInput : (string) $statusInput);
            if ($include !== []) {
                $query->whereIn('status', $include);
            }
        }

        if (array_key_exists('version_id', $validated) && $validated['version_id'] !== null) {
            $query->where('version_id', $validated['version_id']);
        }

        if (array_key_exists('theme_id', $validated) && $validated['theme_id'] !== null) {
            $query->where('theme_id', $validated['theme_id']);
        }

        if (array_key_exists('planning_status', $validated) && $validated['planning_status'] !== null) {
            $query->where('planning_status', $validated['planning_status']);
        }

        $tasks = $query->orderBy('position')->get();

        return response()->json([
            'data' => $tasks->map(fn (Task $t) => $this->taskPayload($t)),
        ]);
    }

    /**
     * @return list<string>
     */
    private function parseStatusFilter(string $raw): array
    {
        $parts = array_map('trim', explode(',', $raw));

        return array_values(array_filter($parts, fn (string $s) => $s !== '' && in_array($s, Task::KANBAN_STATUSES, true)));
    }

    /**
     * @param  array<string>|string  $input
     * @return list<string>
     */
    private function parseStatusFilterFromInput(array|string $input): array
    {
        if (is_array($input)) {
            $parts = [];
            foreach ($input as $s) {
                if (is_string($s) && $s !== '') {
                    $parts[] = trim($s);
                }
            }
        } else {
            $parts = array_map('trim', explode(',', $input));
        }

        return array_values(array_filter($parts, fn (string $s) => $s !== '' && in_array($s, Task::KANBAN_STATUSES, true)));
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
            'value_level' => ['nullable', 'string', Rule::in(Task::MATRIX_LEVELS)],
            'effort_level' => ['nullable', 'string', Rule::in(Task::MATRIX_LEVELS)],
            'eisenhower_quadrant' => ['nullable', 'string', Rule::in(Task::EISENHOWER_QUADRANTS)],
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
            'value_level' => $validated['value_level'] ?? null,
            'effort_level' => $validated['effort_level'] ?? null,
            'eisenhower_quadrant' => $validated['eisenhower_quadrant'] ?? null,
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
            'value_level' => ['nullable', 'string', Rule::in(Task::MATRIX_LEVELS)],
            'effort_level' => ['nullable', 'string', Rule::in(Task::MATRIX_LEVELS)],
            'eisenhower_quadrant' => ['nullable', 'string', Rule::in(Task::EISENHOWER_QUADRANTS)],
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
            'value_level' => $task->value_level,
            'effort_level' => $task->effort_level,
            'eisenhower_quadrant' => $task->eisenhower_quadrant,
            'tags' => $task->tags,
            'created_at' => $task->created_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
        ];
    }
}
