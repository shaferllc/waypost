<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\WaypostMcpApiResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('waypost_list_projects')]
#[Description('Lists Waypost projects you can access (GET /api/projects). Use a Profile API token to see all projects; a project-scoped token returns only that project.')]
class WaypostListProjectsTool extends Tool
{
    public function handle(Request $request): Response
    {
        return WaypostMcpApiResponse::fromDispatch('GET', '/projects');
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
