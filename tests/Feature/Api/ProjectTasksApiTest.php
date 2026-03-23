<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\RoadmapVersion;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectTasksApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_task_creates_in_todo_by_default(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'API',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'New task',
            'body' => 'Details here',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'New task')
            ->assertJsonPath('data.body', 'Details here')
            ->assertJsonPath('data.status', 'todo')
            ->assertJsonPath('data.position', 1);

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'title' => 'New task',
            'status' => 'todo',
            'position' => 1,
        ]);
    }

    public function test_store_task_respects_status_and_version(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'API',
        ]);
        $version = RoadmapVersion::query()->create([
            'project_id' => $project->id,
            'name' => 'v1',
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'Backlog item',
            'status' => 'backlog',
            'version_id' => $version->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'backlog')
            ->assertJsonPath('data.version_id', $version->id);
    }

    public function test_store_task_rejects_foreign_version_id(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'API',
        ]);
        $otherProject = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Other',
        ]);
        $version = RoadmapVersion::query()->create([
            'project_id' => $otherProject->id,
            'name' => 'Their version',
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'Bad',
            'version_id' => $version->id,
        ])->assertUnprocessable();
    }

    public function test_store_task_appends_position_per_status(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'API',
        ]);
        Task::query()->create([
            'project_id' => $project->id,
            'title' => 'First',
            'status' => 'todo',
            'position' => 2,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'Second',
            'status' => 'todo',
        ])
            ->assertCreated()
            ->assertJsonPath('data.position', 3);
    }

    public function test_project_show_includes_versions(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'API',
        ]);
        RoadmapVersion::query()->create([
            'project_id' => $project->id,
            'name' => 'MVP',
            'sort_order' => 1,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'API')
            ->assertJsonCount(1, 'data.versions')
            ->assertJsonPath('data.versions.0.name', 'MVP');
    }

    public function test_project_show_forbidden_for_other_user(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $owner->id,
            'name' => 'Private',
        ]);

        Sanctum::actingAs($intruder);

        $this->getJson("/api/projects/{$project->id}")->assertForbidden();
    }
}
