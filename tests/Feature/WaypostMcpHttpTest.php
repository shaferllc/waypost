<?php

namespace Tests\Feature;

use App\Http\Middleware\LogWaypostMcpHttp;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ProjectCursorTokenIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaypostMcpHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcp_post_returns_503_when_mcp_disabled(): void
    {
        config(['waypost.mcp_enabled' => false]);

        $this->postJson('/mcp/waypost', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => new \stdClass,
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
            ],
        ])
            ->assertStatus(503)
            ->assertJsonPath('mcp_enabled', false);
    }

    public function test_mcp_get_sse_requires_bearer_token(): void
    {
        $this->get('/mcp/waypost', [
            'Accept' => 'text/event-stream',
        ])->assertUnauthorized();
    }

    public function test_mcp_get_without_event_stream_accept_returns_405(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'MCP GET',
        ]);
        $plain = app(ProjectCursorTokenIssuer::class)->issue($project, $user);

        $this->withToken($plain)->get('/mcp/waypost', [
            'Accept' => 'application/json',
        ])->assertStatus(405)->assertHeader('Allow');
    }

    public function test_mcp_get_sse_returns_event_stream_with_token(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'MCP GET SSE',
        ]);
        $plain = app(ProjectCursorTokenIssuer::class)->issue($project, $user);

        $response = $this->withToken($plain)->get('/mcp/waypost', [
            'Accept' => 'text/event-stream',
        ]);

        $response->assertOk()->assertStreamed();
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('Content-Type'));
        $streamed = $response->streamedContent();
        $this->assertStringContainsString('id:', $streamed);
        $this->assertStringContainsString('retry:', $streamed);
    }

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
        ])
            ->assertUnauthorized()
            ->assertHeader('www-authenticate', 'Bearer realm="mcp", error="invalid_token"');
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
        $response->assertHeader('www-authenticate', 'Bearer realm="mcp", error="invalid_token"');
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

    public function test_mcp_tool_waypost_list_projects_works_with_profile_token(): void
    {
        $user = User::factory()->create();
        Project::query()->create([
            'user_id' => $user->id,
            'name' => 'From MCP list',
        ]);

        $plain = $user->createToken('mcp-test')->plainTextToken;

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
                'name' => 'waypost_list_projects',
                'arguments' => new \stdClass,
            ],
        ];

        $second = $this->withToken($plain)
            ->withHeader('MCP-Session-Id', $session)
            ->postJson('/mcp/waypost', $call);

        $second->assertOk();
        $second->assertJsonMissing(['error']);
        $this->assertStringContainsString('From MCP list', $second->getContent());
    }

    public function test_mcp_tool_create_project_succeeds_with_project_scoped_token(): void
    {
        $user = User::factory()->create();
        $home = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Scoped home',
        ]);

        $plain = app(ProjectCursorTokenIssuer::class)->issue($home, $user);

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
                'name' => 'waypost_create_project',
                'arguments' => [
                    'name' => 'Created via MCP',
                    'description' => 'Project token',
                ],
            ],
        ];

        $second = $this->withToken($plain)
            ->withHeader('MCP-Session-Id', $session)
            ->postJson('/mcp/waypost', $call);

        $second->assertOk();
        $second->assertJsonMissing(['error']);
        $this->assertStringContainsString('Created via MCP', $second->getContent());

        $this->assertDatabaseHas('projects', [
            'user_id' => $user->id,
            'name' => 'Created via MCP',
        ]);
    }

    public function test_mcp_create_project_then_create_task_without_project_id_uses_default(): void
    {
        $user = User::factory()->create();
        $home = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Scoped home for default',
        ]);

        $plain = app(ProjectCursorTokenIssuer::class)->issue($home, $user);

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

        $createProject = [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'waypost_create_project',
                'arguments' => [
                    'name' => 'MCP default target project',
                ],
            ],
        ];

        $pRes = $this->withToken($plain)
            ->withHeader('MCP-Session-Id', $session)
            ->postJson('/mcp/waypost', $createProject);

        $pRes->assertOk();
        $pRes->assertJsonMissing(['error']);
        $this->assertStringContainsString('MCP default target project', $pRes->getContent());

        $newProject = Project::query()->where('name', 'MCP default target project')->first();
        $this->assertNotNull($newProject);

        $createTask = [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'waypost_create_task',
                'arguments' => [
                    'title' => 'Task without explicit project_id',
                ],
            ],
        ];

        $tRes = $this->withToken($plain)
            ->withHeader('MCP-Session-Id', $session)
            ->postJson('/mcp/waypost', $createTask);

        $tRes->assertOk();
        $tRes->assertJsonMissing(['error']);
        $this->assertStringContainsString('Task without explicit project_id', $tRes->getContent());

        $this->assertTrue(
            Task::query()
                ->where('project_id', $newProject->id)
                ->where('title', 'Task without explicit project_id')
                ->exists()
        );
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
