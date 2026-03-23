<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TaskDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_post_comment_on_task(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::query()->create([
            'project_id' => $project->id,
            'title' => 'Task',
            'status' => 'backlog',
            'position' => 0,
        ]);

        $this->actingAs($user);

        Volt::test('pages.projects.show', ['project' => $project])
            ->call('openTaskDetail', $task->id)
            ->set('commentBody', 'First note')
            ->call('addComment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'body' => 'First note',
        ]);
    }

    public function test_user_can_create_related_task_link(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $t1 = Task::query()->create([
            'project_id' => $project->id,
            'title' => 'One',
            'status' => 'backlog',
            'position' => 0,
        ]);
        $t2 = Task::query()->create([
            'project_id' => $project->id,
            'title' => 'Two',
            'status' => 'backlog',
            'position' => 1,
        ]);

        $this->actingAs($user);

        Volt::test('pages.projects.show', ['project' => $project])
            ->call('openTaskDetail', $t1->id)
            ->set('linkTargetTaskId', $t2->id)
            ->set('linkType', TaskLink::TYPE_RELATES)
            ->call('addTaskLink')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('task_links', [
            'source_task_id' => min($t1->id, $t2->id),
            'target_task_id' => max($t1->id, $t2->id),
            'type' => TaskLink::TYPE_RELATES,
        ]);
    }
}
