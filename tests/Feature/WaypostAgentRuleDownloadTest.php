<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaypostAgentRuleDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_rule_download_requires_authentication(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'App',
        ]);

        $this->get(route('projects.cursor-rule.agent-activity', $project))
            ->assertRedirect();
    }

    public function test_owner_can_download_cursor_rule_mdc(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'My product',
        ]);

        config(['app.url' => 'https://waypost.example.test']);

        $response = $this->actingAs($user)
            ->get(route('projects.cursor-rule.agent-activity', $project));

        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename="waypost-agent-activity.mdc"');
        $content = $response->getContent();
        $this->assertStringContainsString('waypost_log_agent_phase', $content);
        $this->assertStringContainsString('/api/projects/'.$project->id.'/agent-events', $content);
        $this->assertStringContainsString('https://waypost.example.test', $content);
        $this->assertStringContainsString('My product', $content);
    }
}
