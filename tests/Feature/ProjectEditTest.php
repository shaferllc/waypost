<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_project_from_show_page(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Original',
            'description' => 'Old desc',
        ]);

        $this->actingAs($user);

        Livewire::test('pages.projects.show', ['project' => $project])
            ->call('startEditProject')
            ->assertSet('editingProject', true)
            ->set('editProjectName', 'Renamed')
            ->set('editProjectDescription', 'New description')
            ->set('editProjectUrl', 'https://example.com/app')
            ->call('saveProject')
            ->assertHasNoErrors()
            ->assertSet('editingProject', false);

        $project->refresh();
        $this->assertSame('Renamed', $project->name);
        $this->assertSame('New description', $project->description);
        $this->assertSame('https://example.com/app', $project->url);
    }

    public function test_cancel_edit_discards_changes(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Keep me',
            'description' => null,
        ]);

        $this->actingAs($user);

        Livewire::test('pages.projects.show', ['project' => $project])
            ->call('startEditProject')
            ->set('editProjectName', 'Should not save')
            ->call('cancelEditProject')
            ->assertSet('editingProject', false);

        $this->assertSame('Keep me', $project->fresh()->name);
    }

    public function test_quick_add_kanban_card_creates_task_and_opens_detail(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Board',
            'description' => null,
        ]);

        $this->actingAs($user);

        $component = Livewire::test('pages.projects.show', ['project' => $project])
            ->call('quickAddKanbanCard', 'todo')
            ->assertHasNoErrors();

        $task = Task::query()->where('project_id', $project->id)->sole();
        $this->assertSame('New card', $task->title);
        $this->assertSame('todo', $task->status);
        $component->assertSet('focusedTaskId', $task->id);
    }

    public function test_viewer_cannot_quick_add_kanban_card(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $owner->id,
            'name' => 'Board',
            'description' => null,
        ]);
        $project->members()->attach($viewer->id, ['role' => 'viewer']);

        $this->actingAs($viewer);

        Livewire::test('pages.projects.show', ['project' => $project])
            ->call('quickAddKanbanCard', 'todo');

        $this->assertSame(0, Task::query()->where('project_id', $project->id)->count());
    }
}
