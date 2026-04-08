<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\WaypostMcpApiResponse;
use App\Support\WaypostMcpActiveProjectStore;
use App\Support\WaypostSource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('waypost_log_agent_phase')]
#[Description('Records AI assist start or end in Waypost project activity and changelog (POST /api/projects/{id}/agent-events). Omit project_id to use the MCP default project. Use phase start or end; optional agent slug (cursor, claude_code, …), session_ref, note.')]
class WaypostLogAgentPhaseTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'min:1'],
            'phase' => ['required', 'string', Rule::in(['start', 'end'])],
            'agent' => ['nullable', 'string', 'max:32', Rule::in(WaypostSource::allowedSources())],
            'session_ref' => ['nullable', 'string', 'max:128'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $projectId = app(WaypostMcpActiveProjectStore::class)->resolveProjectId($validated['project_id'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }
        unset($validated['project_id']);

        $body = array_filter($validated, fn ($v) => $v !== null && $v !== '');

        return WaypostMcpApiResponse::fromDispatch('POST', '/projects/'.$projectId.'/agent-events', [], $body);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->nullable(),
            'phase' => $schema->string()->enum(['start', 'end'])->required(),
            'agent' => $schema->string()->nullable(),
            'session_ref' => $schema->string()->nullable(),
            'note' => $schema->string()->nullable(),
        ];
    }
}
