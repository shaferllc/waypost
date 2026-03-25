<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\User;
use App\Services\ProjectCursorTokenIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
