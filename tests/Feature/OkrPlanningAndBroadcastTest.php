<?php

namespace Tests\Feature;

use App\Events\ProjectDataUpdated;
use App\Models\OkrGoal;
use App\Models\OkrKeyResult;
use App\Models\OkrObjective;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class OkrPlanningAndBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_okr_hierarchy_via_livewire(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'OKR project',
        ]);

        $this->actingAs($user);

        Livewire::test('pages.projects.show', ['project' => $project])
            ->set('tab', 'okrs')
            ->set('okrNewGoalTitle', 'Grow revenue')
            ->call('addOkrGoal')
            ->assertHasNoErrors();

        $goal = OkrGoal::query()->where('project_id', $project->id)->firstOrFail();

        Livewire::test('pages.projects.show', ['project' => $project])
            ->set('tab', 'okrs')
            ->set('okrNewObjectiveTitle', 'Expand self-serve')
            ->call('addOkrObjective', $goal->id)
            ->assertHasNoErrors();

        $objective = OkrObjective::query()->where('okr_goal_id', $goal->id)->firstOrFail();

        Livewire::test('pages.projects.show', ['project' => $project])
            ->set('tab', 'okrs')
            ->set('okrNewKrTitle', '10k new signups')
            ->set('okrNewKrProgress', 40)
            ->call('addOkrKeyResult', $objective->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('okr_key_results', [
            'okr_objective_id' => $objective->id,
            'title' => '10k new signups',
            'progress' => 40,
        ]);
    }

    public function test_user_can_link_task_to_okr_and_planning_fields(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Plan',
        ]);
        $goal = OkrGoal::query()->create([
            'project_id' => $project->id,
            'title' => 'Goal',
            'sort_order' => 0,
        ]);
        $objective = OkrObjective::query()->create([
            'okr_goal_id' => $goal->id,
            'title' => 'Objective',
            'sort_order' => 0,
        ]);
        $task = Task::query()->create([
            'project_id' => $project->id,
            'title' => 'Initiative',
            'status' => 'todo',
            'position' => 0,
        ]);

        $this->actingAs($user);

        Livewire::test('pages.projects.show', ['project' => $project])
            ->call('openTaskDetail', $task->id)
            ->set('editTaskOkrObjectiveId', (string) $objective->id)
            ->set('editTaskStartsAt', '2026-03-01')
            ->set('editTaskEndsAt', '2026-04-15')
            ->set('editTaskPlanningStatus', Task::PLANNING_IN_PROGRESS)
            ->call('saveTaskMeta')
            ->assertHasNoErrors();

        $task->refresh();
        $this->assertSame($objective->id, $task->okr_objective_id);
        $this->assertSame('2026-03-01', $task->starts_at->format('Y-m-d'));
        $this->assertSame('2026-04-15', $task->ends_at->format('Y-m-d'));
        $this->assertSame(Task::PLANNING_IN_PROGRESS, $task->planning_status);
    }

    public function test_task_save_dispatches_project_data_updated_event(): void
    {
        Event::fake([ProjectDataUpdated::class]);

        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Broadcast',
        ]);

        $this->actingAs($user);

        Task::query()->create([
            'project_id' => $project->id,
            'title' => 'Card',
            'status' => 'backlog',
            'position' => 0,
        ]);

        Event::assertDispatched(ProjectDataUpdated::class, function (ProjectDataUpdated $e) use ($project): bool {
            return $e->projectId === $project->id;
        });
    }

    public function test_task_update_writes_project_activity_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Activity',
        ]);
        $task = Task::query()->create([
            'project_id' => $project->id,
            'title' => 'T',
            'status' => 'todo',
            'position' => 0,
        ]);

        $this->actingAs($user);

        Livewire::test('pages.projects.show', ['project' => $project])
            ->call('openTaskDetail', $task->id)
            ->set('editTaskPlanningStatus', Task::PLANNING_BEHIND)
            ->call('saveTaskMeta')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'action' => 'task.updated',
        ]);
    }

    public function test_key_result_progress_update_records_activity(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'KR',
        ]);
        $goal = OkrGoal::query()->create([
            'project_id' => $project->id,
            'title' => 'G',
            'sort_order' => 0,
        ]);
        $objective = OkrObjective::query()->create([
            'okr_goal_id' => $goal->id,
            'title' => 'O',
            'sort_order' => 0,
        ]);
        $kr = OkrKeyResult::query()->create([
            'okr_objective_id' => $objective->id,
            'title' => 'KR1',
            'progress' => 10,
            'sort_order' => 0,
        ]);

        $this->actingAs($user);

        Livewire::test('pages.projects.show', ['project' => $project])
            ->set('tab', 'okrs')
            ->call('updateOkrKeyResultProgress', $kr->id, 88)
            ->assertHasNoErrors();

        $this->assertSame(88, $kr->fresh()->progress);
        $this->assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'action' => 'okr.key_result.updated',
        ]);
    }
}
