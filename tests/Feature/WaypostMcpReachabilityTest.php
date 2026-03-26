<?php

namespace Tests\Feature;

use App\Support\WaypostCursorArtifacts;
use Tests\TestCase;

class WaypostMcpReachabilityTest extends TestCase
{
    public function test_mcp_reachability_get_returns_json_without_auth(): void
    {
        $response = $this->getJson('/mcp/waypost/reachable');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('mcp_post_url', WaypostCursorArtifacts::mcpHttpUrl());

        if (str_starts_with(WaypostCursorArtifacts::mcpHttpUrl(), 'http://')) {
            $response->assertJsonPath('http_redirect_warning', fn ($v) => is_string($v) && $v !== '');
        }
    }
}
