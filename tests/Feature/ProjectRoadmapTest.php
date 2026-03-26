<?php

namespace Tests\Feature;

use App\Models\Project;
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
}
