<?php

namespace App\Mcp\Tools;

use App\Support\WaypostMcpActiveProjectStore;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('waypost_set_active_project')]
#[Description('Sets the default Waypost project for this MCP connection (keyed by API token). Omitted project_id on waypost_create_task, waypost_list_tasks, waypost_get_project, etc. uses this value. Use after switching repos or when using a profile token.')]
class WaypostSetActiveProjectTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            app(WaypostMcpActiveProjectStore::class)->remember((int) $validated['project_id']);
        } catch (AuthorizationException $e) {
            return Response::error($e->getMessage() !== '' ? $e->getMessage() : 'Forbidden.');
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        return Response::text(json_encode([
            'ok' => true,
            'project_id' => (int) $validated['project_id'],
            'hint' => 'Subsequent tools may omit project_id until you create another project with scope_followup_to_new_project or call this tool again.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->required(),
        ];
    }
}
