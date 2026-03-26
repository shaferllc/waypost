<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaypostManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_requires_authentication(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'App',
        ]);

        $this->get(route('projects.waypost-manifest', $project))
            ->assertRedirect();
    }

    public function test_owner_can_download_waypost_json(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'My product',
        ]);

        config(['app.url' => 'https://waypost.example.test']);

        $response = $this->actingAs($user)
            ->get(route('projects.waypost-manifest', $project));

        $response->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="waypost.json"')
            ->assertJson([
                'api_base' => 'https://waypost.example.test',
                'mcp_url' => 'https://waypost.example.test/mcp/waypost',
                'mcp_enabled' => true,
                'project_id' => $project->id,
                'project_name' => 'My product',
                'x_waypost_source' => 'ai',
            ]);

        $data = $response->json();
        $this->assertIsArray($data['supported_agent_types'] ?? null);
        $this->assertContains('cursor', $data['supported_agent_types']);
        $this->assertContains('windsurf', $data['supported_agent_types']);
        $this->assertArrayNotHasKey('api_token', $data);
    }
}
