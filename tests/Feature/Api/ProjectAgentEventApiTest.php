<?php

namespace Tests\Feature\Api;

use App\Models\ChangelogEntry;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectAgentEventApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_start_records_changelog_and_activity(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Monitored',
        ]);

        Sanctum::actingAs($user);

        $this->postJson(
            "/api/projects/{$project->id}/agent-events",
            [
                'phase' => 'start',
                'session_ref' => 'sess-abc',
                'note' => 'Refactor auth',
            ],
            ['X-Waypost-Source' => 'ai'],
        )
            ->assertCreated()
            ->assertJsonPath('data.phase', 'start')
            ->assertJsonPath('data.agent', 'ai')
            ->assertJsonPath('data.recorded', true);

        $this->assertDatabaseHas('changelog_entries', [
            'user_id' => $user->id,
            'project_id' => $project->id,
            'action' => 'agent.started',
            'source' => 'ai',
        ]);

        $activity = ProjectActivity::query()
            ->where('project_id', $project->id)
            ->where('action', 'agent.started')
            ->firstOrFail();

        $this->assertSame('sess-abc', $activity->properties['session_ref'] ?? null);
        $this->assertSame('ai', $activity->properties['agent'] ?? null);
        $this->assertSame('ai', $activity->properties['client_source'] ?? null);
    }

    public function test_agent_body_overrides_header_for_label(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Monitored',
        ]);

        Sanctum::actingAs($user);

        $this->postJson(
            "/api/projects/{$project->id}/agent-events",
            [
                'phase' => 'start',
                'agent' => 'windsurf',
            ],
            ['X-Waypost-Source' => 'ai'],
        )
            ->assertCreated()
            ->assertJsonPath('data.agent', 'windsurf');

        $row = ChangelogEntry::query()
            ->where('project_id', $project->id)
            ->where('action', 'agent.started')
            ->firstOrFail();
        $this->assertStringContainsString('(windsurf)', $row->summary);
        $this->assertSame('windsurf', $row->meta['agent'] ?? null);
    }

    public function test_agent_event_rejects_unknown_agent_slug(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Monitored',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/projects/{$project->id}/agent-events", [
            'phase' => 'start',
            'agent' => 'not-a-real-agent-xyz',
        ])->assertUnprocessable();
    }

    public function test_agent_end_records_changelog(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Monitored',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/projects/{$project->id}/agent-events", [
            'phase' => 'end',
            'session_ref' => 'sess-abc',
        ])->assertCreated();

        $this->assertDatabaseHas('changelog_entries', [
            'action' => 'agent.ended',
            'project_id' => $project->id,
        ]);
    }

    public function test_agent_event_requires_auth(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Monitored',
        ]);

        $this->postJson("/api/projects/{$project->id}/agent-events", [
            'phase' => 'start',
        ])->assertUnauthorized();
    }

    public function test_agent_event_forbidden_for_other_users_project(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $owner->id,
            'name' => 'Private',
        ]);

        Sanctum::actingAs($intruder);

        $this->postJson("/api/projects/{$project->id}/agent-events", [
            'phase' => 'start',
        ])->assertForbidden();
    }

    public function test_agent_event_validates_phase(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Monitored',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/projects/{$project->id}/agent-events", [
            'phase' => 'middle',
        ])->assertUnprocessable();
    }

    public function test_changelog_includes_agent_actions(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Monitored',
        ]);

        ChangelogEntry::query()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'source' => 'ai',
            'action' => 'agent.started',
            'summary' => 'AI assist started',
            'meta' => ['phase' => 'start'],
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/changelog?project_id='.$project->id)
            ->assertOk()
            ->assertJsonPath('data.0.action', 'agent.started');
    }
}
