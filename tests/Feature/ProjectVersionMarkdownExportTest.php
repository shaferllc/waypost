<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\RoadmapVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectVersionMarkdownExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_version_markdown_export_returns_markdown_body(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Release train',
        ]);
        $version = RoadmapVersion::query()->create([
            'project_id' => $project->id,
            'name' => 'v1.0',
            'description' => 'First cut',
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('projects.export.version', [$project, $version]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/markdown; charset=UTF-8');
        $this->assertStringContainsString('# v1.0', $response->getContent());
        $this->assertStringContainsString('First cut', $response->getContent());
    }

    public function test_version_markdown_returns_not_found_when_version_belongs_to_another_project(): void
    {
        $user = User::factory()->create();
        $projectA = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'A',
        ]);
        $projectB = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'B',
        ]);
        $versionOnB = RoadmapVersion::query()->create([
            'project_id' => $projectB->id,
            'name' => 'B-only',
            'sort_order' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('projects.export.version', [$projectA, $versionOnB]))
            ->assertNotFound();
    }
}
