<?php

namespace Tests\Feature;

use Tests\TestCase;

class McpRegisterMisrouteTest extends TestCase
{
    public function test_post_register_with_dynamic_client_json_returns_oauth_shaped_error(): void
    {
        $response = $this->postJson('/register', [
            'client_name' => 'Test MCP Client',
            'redirect_uris' => ['http://127.0.0.1/callback'],
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error', 'access_denied')
            ->assertJsonStructure(['error', 'error_description']);

        $this->assertStringContainsString('/mcp/waypost', (string) $response->json('error_description'));
    }

    public function test_post_register_without_oauth_shape_returns_405_json(): void
    {
        $this->postJson('/register', ['foo' => 'bar'])
            ->assertStatus(405)
            ->assertJsonPath('message', 'The POST method is not supported for route register.');
    }
}
