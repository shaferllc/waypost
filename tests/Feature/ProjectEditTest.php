<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
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

        Volt::test('pages.projects.show', ['project' => $project])
            ->call('startEditProject')
            ->assertSet('editingProject', true)
            ->set('editProjectName', 'Renamed')
            ->set('editProjectDescription', 'New description')
            ->call('saveProject')
            ->assertHasNoErrors()
            ->assertSet('editingProject', false);

        $project->refresh();
        $this->assertSame('Renamed', $project->name);
        $this->assertSame('New description', $project->description);
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

        Volt::test('pages.projects.show', ['project' => $project])
            ->call('startEditProject')
            ->set('editProjectName', 'Should not save')
            ->call('cancelEditProject')
            ->assertSet('editingProject', false);

        $this->assertSame('Keep me', $project->fresh()->name);
    }
}
