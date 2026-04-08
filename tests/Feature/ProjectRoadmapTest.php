<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\RoadmapVersion;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectRoadmapTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_show_renders_roadmap_tabs(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Roadmap app',
        ]);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Board')
            ->assertSee('Roadmap')
            ->assertSee('Wishlist')
            ->assertSee('OKRs');
    }

    public function test_kanban_sync_updates_task_status_and_order(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Kanban',
        ]);

        $a = Task::query()->create([
            'project_id' => $project->id,
            'title' => 'A',
            'status' => 'todo',
            'position' => 0,
        ]);
        $b = Task::query()->create([
            'project_id' => $project->id,
            'title' => 'B',
            'status' => 'todo',
            'position' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test('pages.projects.show', ['project' => $project])
            ->call('syncKanban', [
                'backlog' => [],
                'todo' => [$b->id, $a->id],
                'in_progress' => [],
                'done' => [],
            ])
            ->assertHasNoErrors();

        $this->assertSame('todo', $b->fresh()->status);
        $this->assertSame(0, $b->fresh()->position);
        $this->assertSame('todo', $a->fresh()->status);
        $this->assertSame(1, $a->fresh()->position);
    }

    public function test_matrix_sync_sets_value_and_effort_and_clears_unclassified(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Matrix',
        ]);

        $a = Task::query()->create([
            'project_id' => $project->id,
            'title' => 'A',
            'status' => 'todo',
            'position' => 0,
            'value_level' => null,
            'effort_level' => null,
        ]);

        $this->actingAs($user);

        $payload = $this->emptyMatrixSyncPayload();
        $payload['high|low'] = [$a->id];

        Livewire::test('pages.projects.show', ['project' => $project])
            ->set('boardLayout', 'matrix')
            ->call('syncMatrixBoard', $payload)
            ->assertHasNoErrors();

        $a->refresh();
        $this->assertSame(Task::MATRIX_HIGH, $a->value_level);
        $this->assertSame(Task::MATRIX_LOW, $a->effort_level);

        $payload2 = $this->emptyMatrixSyncPayload();
        $payload2['unclassified'] = [$a->id];

        Livewire::test('pages.projects.show', ['project' => $project])
            ->set('boardLayout', 'matrix')
            ->call('syncMatrixBoard', $payload2)
            ->assertHasNoErrors();

        $this->assertNull($a->fresh()->value_level);
        $this->assertNull($a->fresh()->effort_level);
    }

    public function test_eisenhower_sync_sets_quadrant_and_clears_unclassified(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Eisen',
        ]);

        $a = Task::query()->create([
            'project_id' => $project->id,
            'title' => 'A',
            'status' => 'todo',
            'position' => 0,
            'eisenhower_quadrant' => null,
        ]);

        $this->actingAs($user);

        $payload = $this->emptyEisenhowerSyncPayload();
        $payload[Task::EISENHOWER_SCHEDULE] = [$a->id];

        Livewire::test('pages.projects.show', ['project' => $project])
            ->set('boardLayout', 'eisenhower')
            ->call('syncEisenhowerBoard', $payload)
            ->assertHasNoErrors();

        $this->assertSame(Task::EISENHOWER_SCHEDULE, $a->fresh()->eisenhower_quadrant);

        $payload2 = $this->emptyEisenhowerSyncPayload();
        $payload2['unclassified'] = [$a->id];

        Livewire::test('pages.projects.show', ['project' => $project])
            ->set('boardLayout', 'eisenhower')
            ->call('syncEisenhowerBoard', $payload2)
            ->assertHasNoErrors();

        $this->assertNull($a->fresh()->eisenhower_quadrant);
    }

    /**
     * @return array<string, list<int>>
     */
    private function emptyMatrixSyncPayload(): array
    {
        $payload = ['unclassified' => []];
        foreach (Task::MATRIX_LEVELS as $v) {
            foreach (Task::MATRIX_LEVELS as $e) {
                $payload[$v.'|'.$e] = [];
            }
        }

        return $payload;
    }

    /**
     * @return array<string, list<int>>
     */
    private function emptyEisenhowerSyncPayload(): array
    {
        $payload = ['unclassified' => []];
        foreach (Task::EISENHOWER_QUADRANTS as $q) {
            $payload[$q] = [];
        }

        return $payload;
    }

    public function test_ship_version_sets_released_at(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Ship it',
        ]);
        $version = RoadmapVersion::query()->create([
            'project_id' => $project->id,
            'name' => 'M1',
            'sort_order' => 0,
        ]);

        $this->assertNull($version->released_at);

        $this->actingAs($user);

        Livewire::test('pages.projects.show', ['project' => $project])
            ->call('shipVersion', $version->id);

        $this->assertNotNull($version->fresh()->released_at);
    }
}
