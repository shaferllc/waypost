<?php

namespace Tests\Feature;

use App\Models\OkrGoal;
use App\Models\OkrKeyResult;
use App\Models\OkrObjective;
use App\Models\Project;
use App\Models\ProjectShareToken;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRoadmapOkrTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_roadmap_shows_okr_and_timeline_sections(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Customer app',
        ]);
        $goal = OkrGoal::query()->create([
            'project_id' => $project->id,
            'title' => 'Product growth',
            'sort_order' => 0,
        ]);
        $objective = OkrObjective::query()->create([
            'okr_goal_id' => $goal->id,
            'title' => 'Activation',
            'sort_order' => 0,
        ]);
        OkrKeyResult::query()->create([
            'okr_objective_id' => $objective->id,
            'title' => 'Reach 50% onboarding completion',
            'progress' => 60,
            'sort_order' => 0,
        ]);
        Task::query()->create([
            'project_id' => $project->id,
            'title' => 'Onboarding v2',
            'status' => 'todo',
            'position' => 0,
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-02-01',
        ]);

        $token = ProjectShareToken::query()->create([
            'project_id' => $project->id,
            'token' => 'public-test-token-'.str_repeat('a', 32),
            'name' => 'Investors',
        ]);

        $this->get(route('roadmap.public', ['token' => $token->token]))
            ->assertOk()
            ->assertSee('Product growth')
            ->assertSee('Activation')
            ->assertSee('Initiative timeline')
            ->assertSee('Onboarding v2');
    }
}
