<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\User;
use App\Support\WaypostCursorArtifacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaypostCursorMcpInstallLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_cursor_install_url_matches_cursor_deeplink_format_and_decodes_to_server_config(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Demo',
        ]);

        config(['app.url' => 'https://waypost.example.test']);

        $url = WaypostCursorArtifacts::cursorMcpInstallUrl($project);

        $this->assertStringStartsWith('cursor://anysphere.cursor-deeplink/mcp/install?', $url);

        $query = parse_url($url, PHP_URL_QUERY);
        $this->assertIsString($query);
        parse_str($query, $params);
        $this->assertSame('waypost', $params['name'] ?? null);
        $this->assertArrayHasKey('config', $params);

        $decoded = json_decode(base64_decode($params['config'], true), true);
        $this->assertIsArray($decoded);
        $this->assertSame(WaypostCursorArtifacts::mcpServerConfig($project), $decoded);
    }

    public function test_cursor_install_url_can_embed_bearer_token_in_config(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Demo',
        ]);

        config(['app.url' => 'https://waypost.example.test']);

        $plain = 'test-plain-token-abc';
        $url = WaypostCursorArtifacts::cursorMcpInstallUrl($project, $plain);

        $query = parse_url($url, PHP_URL_QUERY);
        $this->assertIsString($query);
        parse_str($query, $params);
        $decoded = json_decode(base64_decode($params['config'], true), true);
        $this->assertIsArray($decoded);
        $this->assertSame('Bearer '.$plain, $decoded['headers']['Authorization'] ?? null);
        $this->assertStringNotContainsString('WAYPOST_API_TOKEN', json_encode($decoded));
    }

    public function test_mcp_servers_snippet_wraps_mcp_server_config(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Demo',
        ]);

        config(['app.url' => 'https://app.test']);

        $snippet = json_decode(WaypostCursorArtifacts::mcpServersSnippetJson($project), true);
        $this->assertSame(['waypost' => WaypostCursorArtifacts::mcpServerConfig($project)], $snippet['mcpServers'] ?? null);
    }
}
