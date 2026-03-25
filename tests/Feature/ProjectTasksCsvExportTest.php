<?php

namespace Tests\Feature;

use App\Models\OkrGoal;
use App\Models\OkrObjective;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTasksCsvExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_export_includes_okr_and_planning_columns(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Export me',
        ]);
        $goal = OkrGoal::query()->create([
            'project_id' => $project->id,
            'title' => 'Strategic',
            'sort_order' => 0,
        ]);
        $objective = OkrObjective::query()->create([
            'okr_goal_id' => $goal->id,
            'title' => 'Objective A',
            'sort_order' => 0,
        ]);
        Task::query()->create([
            'project_id' => $project->id,
            'title' => 'Ship feature',
            'status' => 'in_progress',
            'position' => 0,
            'okr_objective_id' => $objective->id,
            'planning_status' => Task::PLANNING_ON_TIME,
            'starts_at' => '2026-02-01',
            'ends_at' => '2026-03-01',
        ]);

        $response = $this->actingAs($user)->get(route('projects.export.tasks', $project));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('okr_goal', $csv);
        $this->assertStringContainsString('okr_objective', $csv);
        $this->assertStringContainsString('planning_status', $csv);
        $this->assertStringContainsString('starts_at', $csv);
        $this->assertStringContainsString('Strategic', $csv);
        $this->assertStringContainsString('Objective A', $csv);
        $this->assertStringContainsString(Task::PLANNING_ON_TIME, $csv);
    }
}
