<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectLinksApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_link_requires_authentication(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Mine',
        ]);

        $this->postJson("/api/projects/{$project->id}/links", [
            'url' => 'https://example.com',
        ])->assertUnauthorized();
    }

    public function test_store_link_creates_record_with_title(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Mine',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/projects/{$project->id}/links", [
            'url' => 'https://docs.example.com/guide',
            'title' => 'Docs',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Docs')
            ->assertJsonPath('data.url', 'https://docs.example.com/guide');

        $this->assertDatabaseHas('project_links', [
            'project_id' => $project->id,
            'title' => 'Docs',
            'url' => 'https://docs.example.com/guide',
        ]);

        $this->assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'action' => 'project_link.created',
        ]);
    }

    public function test_store_link_defaults_title_to_host(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Mine',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/projects/{$project->id}/links", [
            'url' => 'https://news.ycombinator.com/item?id=1',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'news.ycombinator.com');
    }

    public function test_store_link_forbidden_for_other_users_project(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $owner->id,
            'name' => 'Private',
        ]);

        Sanctum::actingAs($intruder);

        $this->postJson("/api/projects/{$project->id}/links", [
            'url' => 'https://evil.test',
        ])->assertForbidden();
    }

    public function test_store_link_validates_url(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Mine',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/projects/{$project->id}/links", [
            'url' => 'not-a-url',
        ])->assertUnprocessable();
    }
}
