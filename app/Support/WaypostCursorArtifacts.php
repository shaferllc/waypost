<?php

namespace App\Support;

use App\Models\Project;

final class WaypostCursorArtifacts
{
    /**
     * Directory containing package.json for @shaferllc/mcp-server, relative to the opened workspace root.
     */
    public const MCP_SERVER_PACKAGE_DIR = 'mcp/waypost-server';

    /**
     * Single-server MCP definition for Cursor Settings → MCP / install deeplinks (not the mcpServers wrapper).
     *
     * Default: {@see config('waypost.mcp_npm_package')} so editors run `npx -y @scope/pkg@version` — no local
     * clone or npm install in the user’s project (package must be published to npm).
     *
     * If `waypost.mcp_npm_package` is empty, uses tsx + {@see self::MCP_SERVER_PACKAGE_DIR} for maintainers.
     *
     * @return array{command: string, args: list<string>, env: array<string, string>, cwd?: string}
     */
    public static function mcpServerConfig(Project $project): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $env = [
            'WAYPOST_BASE_URL' => $base,
            'WAYPOST_PROJECT_ID' => (string) $project->id,
            'WAYPOST_API_TOKEN' => 'PASTE_YOUR_PROJECT_TOKEN',
        ];

        $spec = trim((string) config('waypost.mcp_npm_package', '@shaferllc/mcp-server@1.0.0'));
        if ($spec === '') {
            return [
                'command' => 'npx',
                'args' => ['tsx', 'src/index.ts'],
                'cwd' => '${workspaceFolder}/'.self::MCP_SERVER_PACKAGE_DIR,
                'env' => $env,
            ];
        }

        return [
            'command' => 'npx',
            'args' => ['-y', $spec],
            'env' => $env,
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
     * Cursor deeplink to register this MCP server (user still sets WAYPOST_API_TOKEN in MCP env).
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

2. MCP install from the Sync tab uses npx to run the published npm package @shaferllc/mcp-server
   (no copy of mcp/waypost-server into this repo required). If your Waypost admin uses local mode
   instead, copy mcp/waypost-server here and run npm install inside it.

3. In Waypost (browser), open this project → Sync tab → reveal or rotate the project API token
   and copy it.

4. Add the token EITHER:
   - into waypost.json as "api_token" (local only; do not commit), OR
   - into your editor MCP env as WAYPOST_API_TOKEN (recommended).

5. Use the project Sync tab → Install in editor (MCP) or Copy MCP config. With the npm package,
   command is npx -y @shaferllc/mcp-server@… (no cwd). Local-mode installs use cwd under
   mcp/waypost-server instead.

6. Reload MCP / restart the editor if needed.

7. Set x_waypost_source in waypost.json (and X-Waypost-Source / agent-events "agent") to match
   your assistant: cursor, github_copilot, windsurf, claude_code, etc. See supported_agent_types
   in waypost.json. Add custom slugs on the server with WAYPOST_EXTRA_CLIENT_SOURCES.

Project: {$name} (id {$project->id})
App URL: {$base}

TXT;
    }
}
