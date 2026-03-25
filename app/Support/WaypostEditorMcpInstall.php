<?php

namespace App\Support;

use App\Models\Project;

/**
 * Editor-specific MCP one-click install URLs and config snippets (non-Cursor).
 *
 * @see https://code.visualstudio.com/docs/copilot/guides/mcp-developer-guide#create-an-mcp-installation-url
 */
final class WaypostEditorMcpInstall
{
    /**
     * Payload for VS Code / VS Code Insiders `vscode:mcp/install?…` handler.
     *
     * @return array{name: string, command: string, args: list<string>, env: array<string, string>}
     */
    public static function vscodeInstallPayload(Project $project): array
    {
        return array_merge(
            ['name' => 'waypost'],
            WaypostCursorArtifacts::mcpServerConfig($project),
        );
    }

    public static function vscodeMcpInstallUrl(Project $project, bool $insiders = false): string
    {
        $scheme = $insiders ? 'vscode-insiders' : 'vscode';
        $json = json_encode(self::vscodeInstallPayload($project), JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode MCP config for VS Code install link');
        }

        return $scheme.':mcp/install?'.rawurlencode($json);
    }

    /**
     * Workspace / user `mcp.json` shape for VS Code (.vscode/mcp.json).
     */
    public static function vscodeMcpJsonSnippet(Project $project): string
    {
        $server = array_merge(
            ['type' => 'stdio'],
            WaypostCursorArtifacts::mcpServerConfig($project),
        );

        return json_encode([
            'servers' => [
                'waypost' => $server,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
