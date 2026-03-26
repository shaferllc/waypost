<?php

namespace Tests\Feature;

use App\Http\Middleware\LogWaypostMcpHttp;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectCursorTokenIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaypostMcpHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcp_initialize_requires_bearer_token(): void
    {
        $this->postJson('/mcp/waypost', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => new \stdClass,
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
            ],
        ])->assertUnauthorized();
    }

    /**
     * Cursor / Streamable HTTP often sends Accept dominated by text/event-stream; without normalization
     * Laravel would redirect unauthenticated MCP POSTs to the HTML login page.
     */
    public function test_mcp_unauthenticated_returns_json_when_accept_is_only_event_stream(): void
    {
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => new \stdClass,
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = $this->call(
            'POST',
            '/mcp/waypost',
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'text/event-stream',
                'CONTENT_TYPE' => 'application/json',
                'HTTP_CONTENT_LENGTH' => (string) strlen($payload),
            ],
            $payload,
        );

        $response->assertUnauthorized();
        $response->assertHeader('content-type', 'application/json');
    }

    public function test_mcp_tool_call_proxies_authenticated_api_request(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'MCP Alpha',
        ]);

        $plain = app(ProjectCursorTokenIssuer::class)->issue($project, $user);

        $init = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => new \stdClass,
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
            ],
        ];

        $first = $this->withToken($plain)->postJson('/mcp/waypost', $init);
        $first->assertOk();
        $session = $first->headers->get('MCP-Session-Id');
        $this->assertNotNull($session);

        $call = [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'waypost_http_request',
                'arguments' => [
                    'method' => 'GET',
                    'path' => '/projects/'.$project->id,
                ],
            ],
        ];

        $second = $this->withToken($plain)
            ->withHeader('MCP-Session-Id', $session)
            ->postJson('/mcp/waypost', $call);

        $second->assertOk();
        $second->assertJsonMissing(['error']);
        $second->assertJsonPath('id', 2);
        $this->assertStringContainsString('MCP Alpha', $second->getContent());
    }

    public function test_mcp_logs_channel_adds_request_id_header_when_waypost_mcp_log_requests_enabled(): void
    {
        config(['waypost.mcp_log_requests' => true]);

        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'MCP Log',
        ]);

        $plain = app(ProjectCursorTokenIssuer::class)->issue($project, $user);

        $response = $this->withToken($plain)->postJson('/mcp/waypost', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => new \stdClass,
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
            ],
        ]);

        $response->assertOk();
        $response->assertHeader(LogWaypostMcpHttp::REQUEST_ID_HEADER);
    }
}
