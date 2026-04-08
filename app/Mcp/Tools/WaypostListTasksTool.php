<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\WaypostMcpApiResponse;
use App\Support\WaypostMcpActiveProjectStore;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('waypost_list_tasks')]
#[Description('Lists tasks for a project (GET /api/projects/{id}/tasks). Omit project_id to use the MCP default project. Optional filters: status (comma-separated kanban statuses), exclude_status, open_only (omit done), version_id, theme_id, planning_status.')]
class WaypostListTasksTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'max:500'],
            'exclude_status' => ['nullable', 'string', 'max:500'],
            'open_only' => ['nullable', 'boolean'],
            'version_id' => ['nullable', 'integer', 'min:1'],
            'theme_id' => ['nullable', 'integer', 'min:1'],
            'planning_status' => ['nullable', 'string', 'max:64'],
        ]);

        $query = [];
        foreach (['status', 'exclude_status', 'version_id', 'theme_id', 'planning_status'] as $key) {
            if (array_key_exists($key, $validated) && $validated[$key] !== null && $validated[$key] !== '') {
                $query[$key] = $validated[$key];
            }
        }
        if (array_key_exists('open_only', $validated) && $validated['open_only'] === true) {
            $query['open_only'] = true;
        }

        try {
            $projectId = app(WaypostMcpActiveProjectStore::class)->resolveProjectId($validated['project_id'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        return WaypostMcpApiResponse::fromDispatch('GET', '/projects/'.$projectId.'/tasks', $query);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->nullable(),
            'status' => $schema->string()->nullable(),
            'exclude_status' => $schema->string()->nullable(),
            'open_only' => $schema->boolean()->nullable(),
            'version_id' => $schema->integer()->nullable(),
            'theme_id' => $schema->integer()->nullable(),
            'planning_status' => $schema->string()->nullable(),
        ];
    }
}
