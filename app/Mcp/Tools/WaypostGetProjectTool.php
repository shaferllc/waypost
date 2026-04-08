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

#[Name('waypost_get_project')]
#[Description('Returns one project with themes and roadmap versions (GET /api/projects/{id}). Omit project_id to use the MCP default project. Use version_id and theme_id when creating or updating tasks.')]
class WaypostGetProjectTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $projectId = app(WaypostMcpActiveProjectStore::class)->resolveProjectId($validated['project_id'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        return WaypostMcpApiResponse::fromDispatch('GET', '/projects/'.$projectId);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->nullable(),
        ];
    }
}
