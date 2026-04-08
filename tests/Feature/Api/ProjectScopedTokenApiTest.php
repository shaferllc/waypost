<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\User;
use App\Services\ProjectCursorTokenIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectScopedTokenApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_scoped_token_only_allows_matching_project_routes(): void
    {
        $user = User::factory()->create();
        $projectA = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Alpha',
        ]);
        $projectB = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Beta',
        ]);

        $plain = app(ProjectCursorTokenIssuer::class)->issue($projectA, $user);

        $this->withToken($plain)
            ->getJson("/api/projects/{$projectB->id}")
            ->assertForbidden();

        $this->withToken($plain)
            ->getJson("/api/projects/{$projectA->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Alpha');
    }

    public function test_scoped_token_projects_index_returns_only_that_project(): void
    {
        $user = User::factory()->create();
        Project::query()->create([
            'user_id' => $user->id,
            'name' => 'One',
        ]);
        $two = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Two',
        ]);

        $plain = app(ProjectCursorTokenIssuer::class)->issue($two, $user);

        $this->withToken($plain)
            ->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Two');
    }

    public function test_unscoped_token_can_list_all_owned_projects(): void
    {
        $user = User::factory()->create();
        Project::query()->create(['user_id' => $user->id, 'name' => 'One']);
        Project::query()->create(['user_id' => $user->id, 'name' => 'Two']);

        $plain = $user->createToken('full access')->plainTextToken;

        $this->withToken($plain)
            ->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_scoped_token_cannot_create_project(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Scoped home',
        ]);

        $plain = app(ProjectCursorTokenIssuer::class)->issue($project, $user);

        $this->withToken($plain)
            ->postJson('/api/projects', [
                'name' => 'Another project',
            ])
            ->assertForbidden()
            ->assertJsonFragment(['message' => 'Project-scoped tokens cannot create projects.']);
    }

    public function test_create_project_with_issue_sync_token_returns_manifest_and_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/projects', [
            'name' => 'Bootstrapped',
            'issue_sync_token' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Bootstrapped')
            ->assertJsonStructure([
                'data' => ['id', 'name'],
                'sync_token',
                'waypost_json',
                'cursor_mcp_install_url',
                '_bootstrap_hint',
            ]);

        $this->assertIsString($response->json('sync_token'));
        $this->assertStringContainsString('|', (string) $response->json('sync_token'));
        $this->assertStringContainsString('"project_id"', (string) $response->json('waypost_json'));
        $this->assertStringContainsString('api_token', (string) $response->json('waypost_json'));
        $this->assertStringStartsWith('cursor://', (string) $response->json('cursor_mcp_install_url'));
    }
}
