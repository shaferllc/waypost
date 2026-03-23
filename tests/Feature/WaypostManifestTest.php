<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaypostManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_requires_authentication(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'App',
        ]);

        $this->get(route('projects.waypost-manifest', $project))
            ->assertRedirect();
    }

    public function test_owner_can_download_waypost_json(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'My product',
        ]);

        config(['app.url' => 'https://waypost.example.test']);

        $this->actingAs($user)
            ->get(route('projects.waypost-manifest', $project))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="waypost.json"')
            ->assertJson([
                'api_base' => 'https://waypost.example.test',
                'project_id' => $project->id,
                'project_name' => 'My product',
            ]);
    }
}
