<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\WaypostMcpApiResponse;
use App\Models\Task;
use App\Support\WaypostMcpActiveProjectStore;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('waypost_update_task')]
#[Description('Updates a task (PATCH /api/projects/{project}/tasks/{task}). Omit project_id to use the MCP default project. Include only fields to change: title, body, status, version_id, theme_id, priority, dates, planning_status, value_level, effort_level, eisenhower_quadrant, tags, position.')]
class WaypostUpdateTaskTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'min:1'],
            'task_id' => ['required', 'integer', 'min:1'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', Rule::in(Task::KANBAN_STATUSES)],
            'version_id' => ['nullable', 'integer', 'min:1'],
            'theme_id' => ['nullable', 'integer', 'min:1'],
            'priority' => ['nullable', 'integer', Rule::in([Task::PRIORITY_LOW, Task::PRIORITY_NORMAL, Task::PRIORITY_HIGH])],
            'due_date' => ['nullable', 'string', 'max:32'],
            'planning_status' => ['nullable', 'string', Rule::in(Task::PLANNING_STATUSES)],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
            'position' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'value_level' => ['nullable', 'string', Rule::in(Task::MATRIX_LEVELS)],
            'effort_level' => ['nullable', 'string', Rule::in(Task::MATRIX_LEVELS)],
            'eisenhower_quadrant' => ['nullable', 'string', Rule::in(Task::EISENHOWER_QUADRANTS)],
        ]);

        try {
            $projectId = app(WaypostMcpActiveProjectStore::class)->resolveProjectId($validated['project_id'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }
        $taskId = $validated['task_id'];
        unset($validated['project_id'], $validated['task_id']);

        $body = array_filter($validated, fn ($v) => $v !== null);
        if ($body === []) {
            return Response::error('Provide at least one field to update.');
        }

        return WaypostMcpApiResponse::fromDispatch('PATCH', '/projects/'.$projectId.'/tasks/'.$taskId, [], $body);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->nullable(),
            'task_id' => $schema->integer()->required(),
            'title' => $schema->string()->nullable(),
            'body' => $schema->string()->nullable(),
            'status' => $schema->string()->nullable(),
            'version_id' => $schema->integer()->nullable(),
            'theme_id' => $schema->integer()->nullable(),
            'priority' => $schema->integer()->nullable(),
            'due_date' => $schema->string()->nullable(),
            'planning_status' => $schema->string()->nullable(),
            'tags' => $schema->array()->nullable(),
            'position' => $schema->integer()->nullable(),
            'value_level' => $schema->string()->nullable(),
            'effort_level' => $schema->string()->nullable(),
            'eisenhower_quadrant' => $schema->string()->nullable(),
        ];
    }
}
