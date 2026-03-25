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
        $this->assertSame('stdio', $parsed['servers']['waypost']['type'] ?? null);
        $this->assertSame(WaypostCursorArtifacts::mcpServerConfig($project), array_diff_key($parsed['servers']['waypost'], ['type' => true]));
    }
}
