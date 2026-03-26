<?php

namespace App\Support;

use App\Models\Project;
use Laravel\Mcp\Server;

final class WaypostCursorArtifacts
{
    /**
     * MCP-Protocol-Version header value negotiated with {@see Server} (see MCP Streamable HTTP spec).
     */
    public const MCP_PROTOCOL_VERSION = '2025-11-25';

    /**
     * Root URL for Sync downloads, MCP env (`WAYPOST_BASE_URL`), and `waypost.json` `api_base`.
     *
     * Uses {@see config('waypost.public_url')} when set, otherwise {@see config('app.url')}.
     */
    public static function publicBaseUrl(): string
    {
        $override = trim((string) config('waypost.public_url', ''));

        if ($override !== '') {
            return rtrim($override, '/');
        }

        return rtrim((string) config('app.url'), '/');
    }

    /**
     * Single-server MCP definition for Cursor Settings → MCP / install deeplinks (not the mcpServers wrapper).
     *
     * Uses the app-hosted Streamable HTTP MCP endpoint. Without {@see $embedBearerPlaintext}, the Authorization
     * header uses {@see self::mcpAuthorizationHeaderEnvPlaceholder()} so editors read `WAYPOST_API_TOKEN` from env.
     * Pass the project token when generating a one-click Cursor install link.
     *
     * @return array{url: string, headers: array<string, string>}
     */
    public static function mcpServerConfig(Project $project, ?string $embedBearerPlaintext = null): array
    {
        $authorization = (is_string($embedBearerPlaintext) && $embedBearerPlaintext !== '')
            ? 'Bearer '.$embedBearerPlaintext
            : self::mcpAuthorizationHeaderEnvPlaceholder();

        return [
            'url' => self::mcpHttpUrl(),
            'headers' => [
                'Authorization' => $authorization,
                'Accept' => 'application/json, text/event-stream',
                'MCP-Protocol-Version' => self::MCP_PROTOCOL_VERSION,
            ],
        ];
    }

    public static function mcpHttpUrl(): string
    {
        return self::mcpEndpointSchemeAdjustedBase().'/mcp/waypost';
    }

    /**
     * GET JSON probe for TLS / routing (no auth). Cursor uses POST on {@see mcpHttpUrl()} only.
     */
    public static function mcpReachabilityUrl(): string
    {
        return self::mcpEndpointSchemeAdjustedBase().'/mcp/waypost/reachable';
    }

    /**
     * Public base URL with optional http→https upgrade for MCP-only paths (Herd/Valet).
     */
    private static function mcpEndpointSchemeAdjustedBase(): string
    {
        $base = self::publicBaseUrl();
        if (! str_starts_with($base, 'http://')) {
            return $base;
        }

        $host = parse_url($base, PHP_URL_HOST);
        $host = is_string($host) ? $host : '';

        $cfg = config('waypost.mcp_upgrade_http_to_https');
        $upgrade = $cfg === true
            || ($cfg === null && str_ends_with($host, '.test'));

        if (! $upgrade) {
            return $base;
        }

        return 'https://'.substr($base, 7);
    }

    public static function mcpAuthorizationHeaderEnvPlaceholder(): string
    {
        return 'Bearer ${env:WAYPOST_API_TOKEN}';
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
     * Cursor deeplink to register this MCP server.
     *
     * @see https://cursor.com/docs/context/mcp/install-links
     */
    public static function cursorMcpInstallUrl(Project $project, ?string $embedBearerPlaintext = null): string
    {
        $json = json_encode(self::mcpServerConfig($project, $embedBearerPlaintext), JSON_UNESCAPED_SLASHES);
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
    public static function manifestPayload(Project $project, ?string $apiTokenPlaintext = null): array
    {
        $payload = [
            'api_base' => self::publicBaseUrl(),
            'mcp_url' => self::mcpHttpUrl(),
            'mcp_enabled' => (bool) config('waypost.mcp_enabled', true),
            'project_id' => $project->id,
            'project_name' => $project->name,
            'x_waypost_source' => (string) config('waypost.manifest_x_waypost_source', 'ai'),
            /** Use one of these for X-Waypost-Source / agent-events "agent" (plus any WAYPOST_EXTRA_CLIENT_SOURCES). */
            'supported_agent_types' => WaypostSource::suggestedAgentTypes(),
        ];

        if ($apiTokenPlaintext !== null && $apiTokenPlaintext !== '') {
            $payload['api_token'] = $apiTokenPlaintext;
        }

        return $payload;
    }

    public static function manifestJson(Project $project, bool $includeBundleNote = false, ?string $apiTokenPlaintext = null): string
    {
        $payload = self::manifestPayload($project, $apiTokenPlaintext);
        if ($includeBundleNote) {
            $payload['_setup'] = ($apiTokenPlaintext !== null && $apiTokenPlaintext !== '')
                ? 'This bundle includes api_token for first-time setup. Remove api_token from waypost.json before committing. For editor MCP, set WAYPOST_API_TOKEN in the environment (see mcp_url) — never commit secrets.'
                : 'Paste your project API token from Waypost (project → Sync tab) into api_token, or set WAYPOST_API_TOKEN in the editor environment for MCP (see mcp_url) — never commit secrets.';
        }

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    }

    /**
     * Remember the plaintext project token for one ZIP download (same browser session) after project create.
     */
    public static function flashCursorSetupToken(int $projectId, string $plainTextToken): void
    {
        session()->put(self::cursorSetupTokenSessionKey($projectId), $plainTextToken);
    }

    /**
     * Take the one-time token for embedding in waypost.json inside the Cursor setup ZIP, if present.
     */
    public static function pullCursorSetupToken(int $projectId): ?string
    {
        $t = session()->pull(self::cursorSetupTokenSessionKey($projectId));

        return is_string($t) && $t !== '' ? $t : null;
    }

    public static function forgetCursorSetupToken(int $projectId): void
    {
        session()->forget(self::cursorSetupTokenSessionKey($projectId));
    }

    private static function cursorSetupTokenSessionKey(int $projectId): string
    {
        return 'waypost_cursor_setup_token.'.$projectId;
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
            [(string) $project->id, $name, self::publicBaseUrl()],
            $stub,
        );
    }

    public static function bundleReadme(Project $project): string
    {
        $base = self::publicBaseUrl();
        $name = str_replace(["\r", "\n"], ' ', $project->name);

        return <<<TXT
Waypost + Cursor — quick setup
==============================

1. Extract this ZIP to your repository root (the same folder you open in Cursor).
   You should get:
   - waypost.json
   - .cursor/rules/waypost-agent-activity.mdc
   - this file (WAYPOST-CURSOR-README.txt)

2. MCP is served over HTTPS from this Waypost app (see mcp_url in waypost.json). No npm or local
   MCP package is required in your repo.

3. In Waypost (browser), open this project → Sync tab → reveal or rotate the project API token
   and copy it.

4. Add the token EITHER:
   - into waypost.json as "api_token" (local only; do not commit), OR
   - into your editor environment as WAYPOST_API_TOKEN (recommended for MCP Authorization).

5. Use the project Sync tab → Add to Cursor / Copy MCP config. The MCP URL and Bearer header
   reference WAYPOST_API_TOKEN from your environment.

6. Reload MCP / restart the editor if needed.

7. Set x_waypost_source in waypost.json (and X-Waypost-Source / agent-events "agent") to match
   your assistant: cursor, github_copilot, windsurf, claude_code, etc. See supported_agent_types
   in waypost.json. Add custom slugs on the server with WAYPOST_EXTRA_CLIENT_SOURCES.

Project: {$name} (id {$project->id})
App URL: {$base}

TXT;
    }
}
