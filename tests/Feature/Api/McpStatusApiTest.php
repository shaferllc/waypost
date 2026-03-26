<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Support\WaypostCursorArtifacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class McpStatusApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcp_status_requires_authentication(): void
    {
        $this->getJson('/api/mcp/status')->assertUnauthorized();
    }

    public function test_mcp_status_returns_payload_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/mcp/status')
            ->assertOk()
            ->assertJsonPath('authenticated_user_id', $user->id)
            ->assertJsonPath('token_project_scope', null)
            ->assertJsonPath('mcp_http_url', WaypostCursorArtifacts::mcpHttpUrl())
            ->assertJsonPath('mcp_reachability_url', WaypostCursorArtifacts::mcpReachabilityUrl())
            ->assertJsonStructure([
                'app_name',
                'laravel_version',
                'api_url',
                'mcp_http_url',
                'mcp_reachability_url',
                'authenticated_user_id',
                'token_project_scope',
            ]);
    }
}
