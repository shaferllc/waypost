<?php

namespace Tests\Feature\Api;

use App\Models\ChangelogEntry;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChangelogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_changelog_requires_authentication(): void
    {
        $this->getJson('/api/changelog')->assertUnauthorized();
    }

    public function test_changelog_lists_entries_for_user(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'P',
        ]);
        ChangelogEntry::query()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'source' => 'mcp',
            'action' => 'task.created',
            'summary' => 'Created task: X',
            'meta' => ['task_id' => 1],
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/changelog')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.source', 'mcp')
            ->assertJsonPath('data.0.action', 'task.created');
    }

    public function test_task_create_with_mcp_source_logs_changelog(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'P',
        ]);

        Sanctum::actingAs($user);

        $this->postJson(
            "/api/projects/{$project->id}/tasks",
            ['title' => 'From MCP'],
            ['X-Waypost-Source' => 'mcp']
        )->assertCreated();

        $this->assertDatabaseHas('changelog_entries', [
            'user_id' => $user->id,
            'project_id' => $project->id,
            'source' => 'mcp',
            'action' => 'task.created',
        ]);
    }
}
