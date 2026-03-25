<?php

namespace App\Support;

use App\Models\Project;

final class WaypostCursorArtifacts
{
    /**
     * Single-server MCP definition for Cursor Settings → MCP / install deeplinks (not the mcpServers wrapper).
     *
     * @return array{command: string, args: list<string>, env: array<string, string>}
     */
    public static function mcpServerConfig(Project $project): array
    {
        $base = rtrim((string) config('app.url'), '/');

        return [
            'command' => 'node',
            'args' => ['/ABSOLUTE/PATH/TO/mcp/waypost-server/dist/index.js'],
            'env' => [
                'WAYPOST_BASE_URL' => $base,
                'WAYPOST_PROJECT_ID' => (string) $project->id,
                'WAYPOST_API_TOKEN' => 'PASTE_YOUR_PROJECT_TOKEN',
            ],
        ];
    }

    /**
     * Pretty-printed JSON for merging into Cursor MCP settings (mcpServers.waypost).
     */
    public static function mcpServersSnippetJson(Project $project): string
    {
        return json_encode([
            'mcpServers' => [
                'waypost' => self::mcpServerConfig($project),
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Cursor deeplink to register this MCP server (user still fixes args path and sets WAYPOST_API_TOKEN).
     *
     * @see https://cursor.com/docs/context/mcp/install-links
     */
    public static function cursorMcpInstallUrl(Project $project): string
    {
        $json = json_encode(self::mcpServerConfig($project), JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode MCP config for Cursor install link');
        }

        $config = base64_encode($json);

        return 'cursor://anysphere.cursor-deeplink/mcp/install?'
            .http_build_query(['name' => 'waypost', 'config' => $config], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array<string, mixed>
     */
    public static function manifestPayload(Project $project): array
    {
        return [
            'api_base' => rtrim((string) config('app.url'), '/'),
            'project_id' => $project->id,
            'project_name' => $project->name,
            'x_waypost_source' => (string) config('waypost.manifest_x_waypost_source', 'ai'),
            /** Use one of these for X-Waypost-Source / agent-events "agent" (plus any WAYPOST_EXTRA_CLIENT_SOURCES). */
            'supported_agent_types' => WaypostSource::suggestedAgentTypes(),
        ];
    }

    public static function manifestJson(Project $project, bool $includeBundleNote = false): string
    {
        $payload = self::manifestPayload($project);
        if ($includeBundleNote) {
            $payload['_setup'] = 'Paste your project API token from Waypost (project → Sync tab) into api_token, or set WAYPOST_API_TOKEN in MCP env only — never commit secrets.';
        }

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    }

    public static function agentRuleMdcBody(Project $project): string
    {
        $stubPath = resource_path('cursor-rules/waypost-agent-activity.mdc.stub');
        if (! is_readable($stubPath)) {
            throw new \RuntimeException('Rule template missing');
        }

        $stub = file_get_contents($stubPath);
        if ($stub === false) {
            throw new \RuntimeException('Rule template unreadable');
        }

        $name = str_replace(["\r", "\n"], ' ', $project->name);

        return str_replace(
            ['__PROJECT_ID__', '__PROJECT_NAME__', '__API_BASE__'],
            [(string) $project->id, $name, rtrim((string) config('app.url'), '/')],
            $stub,
        );
    }

    public static function bundleReadme(Project $project): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $name = str_replace(["\r", "\n"], ' ', $project->name);

        return <<<TXT
Waypost + Cursor — quick setup
==============================

1. Extract this ZIP to your repository root (the same folder you open in Cursor).
   You should get:
   - waypost.json
   - .cursor/rules/waypost-agent-activity.mdc
   - this file (WAYPOST-CURSOR-README.txt)

2. In Waypost (browser), open this project → "Sync with Cursor" → reveal or rotate the
   project API token and copy it.

3. Add the token EITHER:
   - into waypost.json as "api_token" (local only; do not commit), OR
   - into Cursor → Settings → MCP → env as WAYPOST_API_TOKEN (recommended).

4. In Cursor → Settings → MCP, merge the JSON from "Copy MCP config" on the project page
   (or build it yourself). Set "args" to the absolute path of:
   mcp/waypost-server/dist/index.js
   inside your Waypost git clone (or your install path).

5. Reload MCP / restart Cursor if needed.

6. Set x_waypost_source in waypost.json (and X-Waypost-Source / agent-events "agent") to match
   your assistant: cursor, github_copilot, windsurf, claude_code, etc. See supported_agent_types
   in waypost.json. Add custom slugs on the server with WAYPOST_EXTRA_CLIENT_SOURCES.

Project: {$name} (id {$project->id})
App URL: {$base}

TXT;
    }
}
