<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\User;
use App\Support\WaypostCursorArtifacts;
use App\Support\WaypostEditorMcpInstall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaypostEditorMcpInstallTest extends TestCase
{
    use RefreshDatabase;

    public function test_vscode_install_url_uses_encoded_json_query(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Demo',
        ]);

        config(['app.url' => 'https://waypost.example.test']);

        $url = WaypostEditorMcpInstall::vscodeMcpInstallUrl($project, false);
        $this->assertStringStartsWith('vscode:mcp/install?', $url);

        $query = substr($url, strlen('vscode:mcp/install?'));
        $decoded = json_decode(rawurldecode($query), true);
        $this->assertIsArray($decoded);
        $this->assertSame(WaypostEditorMcpInstall::vscodeInstallPayload($project), $decoded);
    }

    public function test_vscode_insiders_uses_insiders_scheme(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Demo',
        ]);

        config(['app.url' => 'https://app.test']);

        $url = WaypostEditorMcpInstall::vscodeMcpInstallUrl($project, true);
        $this->assertStringStartsWith('vscode-insiders:mcp/install?', $url);
    }

    public function test_vscode_mcp_json_snippet_matches_servers_shape(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Demo',
        ]);

        config(['app.url' => 'https://app.test']);

        $parsed = json_decode(WaypostEditorMcpInstall::vscodeMcpJsonSnippet($project), true);
        $this->assertIsArray($parsed);
        $this->assertArrayHasKey('servers', $parsed);
        $w = $parsed['servers']['waypost'];
        $this->assertSame('http', $w['type'] ?? null);
        $cfg = WaypostCursorArtifacts::mcpServerConfig();
        $this->assertSame($cfg['url'], $w['url'] ?? null);
        $this->assertSame($cfg['headers'], $w['headers'] ?? null);
    }

    public function test_mcp_server_config_uses_https_mcp_endpoint(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Demo',
        ]);

        config(['app.url' => 'https://app.test']);

        $cfg = WaypostCursorArtifacts::mcpServerConfig();
        $this->assertSame('streamableHttp', $cfg['type'] ?? null);
        $this->assertSame('https://app.test/mcp/waypost', $cfg['url']);
        $this->assertArrayHasKey('headers', $cfg);
        $this->assertStringContainsString('WAYPOST_API_TOKEN', (string) ($cfg['headers']['Authorization'] ?? ''));
        $this->assertSame('application/json, text/event-stream', $cfg['headers']['Accept'] ?? null);
        $this->assertSame(WaypostCursorArtifacts::MCP_PROTOCOL_VERSION, $cfg['headers']['MCP-Protocol-Version'] ?? null);
    }
}
