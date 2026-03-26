<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\ProjectCursorTokenIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class ProjectCursorSetupZipAfterRotateTest extends TestCase
{
    use RefreshDatabase;

    public function test_zip_includes_token_after_rotate_cursor_token_from_project_page(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive not available');
        }

        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Rotate ZIP',
        ]);

        app(ProjectCursorTokenIssuer::class)->issue($project, $user);

        $this->actingAs($user);

        $component = Livewire::test('pages.projects.show', ['project' => $project]);
        $component->call('rotateCursorToken');

        $plaintext = $component->get('revealedCursorToken');
        $this->assertIsString($plaintext);
        $this->assertNotSame('', $plaintext);

        $response = $this->get(route('projects.waypost-cursor-setup', $project));

        $response->assertOk();
        $tmp = tempnam(sys_get_temp_dir(), 'wpzip-rot');
        $this->assertNotFalse($tmp);
        file_put_contents($tmp, $response->streamedContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp));
        $manifest = $zip->getFromName('waypost.json');
        $this->assertIsString($manifest);
        $data = json_decode($manifest, true);
        $this->assertIsArray($data);
        $this->assertSame($plaintext, $data['api_token'] ?? null);
        $zip->close();
        unlink($tmp);
    }
}
