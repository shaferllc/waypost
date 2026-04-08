<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\WaypostMcpApiResponse;
use App\Support\WaypostMcpActiveProjectStore;
use App\Support\WaypostMcpInternalApi;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('waypost_create_project')]
#[Description('Creates a new Waypost project (POST /api/projects). Works with a Profile token or a project Sync token when called through MCP (direct API calls with a project token still return 403). By default scope_followup_to_new_project is true so the next tools can omit project_id. Set issue_sync_token true when wiring a repo: response includes sync_token, waypost_json, cursor_mcp_install_url.')]
class WaypostCreateProjectTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'url' => ['nullable', 'string', 'max:2048'],
            'issue_sync_token' => ['nullable', 'boolean'],
            'scope_followup_to_new_project' => ['nullable', 'boolean'],
        ]);

        $scopeFollowup = $validated['scope_followup_to_new_project'] ?? true;

        $body = ['name' => $validated['name']];
        if (array_key_exists('description', $validated) && $validated['description'] !== null) {
            $body['description'] = $validated['description'];
        }
        if (array_key_exists('url', $validated) && $validated['url'] !== null) {
            $body['url'] = $validated['url'];
        }
        if (array_key_exists('issue_sync_token', $validated)) {
            $body['issue_sync_token'] = (bool) $validated['issue_sync_token'];
        }

        try {
            $symfony = WaypostMcpInternalApi::dispatch('POST', '/projects', [], $body);
        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return Response::error('Failed to call the Waypost API.');
        }

        if ($symfony->getStatusCode() === 201 && $scopeFollowup) {
            $decoded = json_decode($symfony->getContent(), true);
            $id = is_array($decoded) ? ($decoded['data']['id'] ?? null) : null;
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                app(WaypostMcpActiveProjectStore::class)->remember((int) $id);
            }
        }

        return WaypostMcpApiResponse::fromSymfony($symfony);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'description' => $schema->string()->nullable(),
            'url' => $schema->string()->nullable(),
            'issue_sync_token' => $schema->boolean()->nullable(),
            'scope_followup_to_new_project' => $schema->boolean()->nullable(),
        ];
    }
}
