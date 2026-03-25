<?php

namespace App\Support;

use App\Models\Project;

final class WaypostCursorArtifacts
{
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
            $payload['_setup'] = 'Paste your project API token from Waypost (Sync with Cursor) into api_token, or set WAYPOST_API_TOKEN in MCP env only — never commit secrets.';
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

Project: {$name} (id {$project->id})
App URL: {$base}

TXT;
    }
}
