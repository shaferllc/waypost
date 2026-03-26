<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class WaypostCursorSetupZipTest extends TestCase
{
    use RefreshDatabase;

    public function test_zip_requires_authentication(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive not available');
        }

        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'App',
        ]);

        $this->get(route('projects.waypost-cursor-setup', $project))
            ->assertRedirect();
    }

    public function test_owner_downloads_zip_with_manifest_rule_and_readme(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive not available');
        }

        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'My product',
        ]);

        config(['app.url' => 'https://waypost.example.test']);

        $response = $this->actingAs($user)
            ->get(route('projects.waypost-cursor-setup', $project));

        $response->assertOk();
        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('waypost-cursor-setup.zip', $disposition);

        $tmp = tempnam(sys_get_temp_dir(), 'wpzip');
        $this->assertNotFalse($tmp);
        file_put_contents($tmp, $response->streamedContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp));
        $this->assertNotFalse($zip->locateName('waypost.json'));
        $this->assertNotFalse($zip->locateName('.cursor/rules/waypost-agent-activity.mdc'));
        $this->assertNotFalse($zip->locateName('WAYPOST-CURSOR-README.txt'));
        $manifest = $zip->getFromName('waypost.json');
        $this->assertIsString($manifest);
        $this->assertStringContainsString('"project_id": '.$project->id, $manifest);
        $this->assertStringContainsString('_setup', $manifest);
        $this->assertStringContainsString('supported_agent_types', $manifest);
        $decoded = json_decode($manifest, true);
        $this->assertIsArray($decoded);
        $this->assertArrayNotHasKey('api_token', $decoded);
        $zip->close();
        unlink($tmp);
    }

    public function test_zip_includes_api_token_once_when_flashed_in_session(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive not available');
        }

        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Z',
        ]);

        $plaintext = '9|one-time-token-for-zip-test';

        $first = $this->actingAs($user)
            ->withSession([
                'waypost_cursor_setup_token.'.$project->id => $plaintext,
            ])
            ->get(route('projects.waypost-cursor-setup', $project));

        $first->assertOk();
        $tmp = tempnam(sys_get_temp_dir(), 'wpzip');
        $this->assertNotFalse($tmp);
        file_put_contents($tmp, $first->streamedContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp));
        $manifest = $zip->getFromName('waypost.json');
        $this->assertIsString($manifest);
        $data = json_decode($manifest, true);
        $this->assertIsArray($data);
        $this->assertSame($plaintext, $data['api_token'] ?? null);
        $zip->close();
        unlink($tmp);

        $second = $this->actingAs($user)
            ->get(route('projects.waypost-cursor-setup', $project));

        $second->assertOk();
        $tmp2 = tempnam(sys_get_temp_dir(), 'wpzip2');
        $this->assertNotFalse($tmp2);
        file_put_contents($tmp2, $second->streamedContent());
        $zip2 = new ZipArchive;
        $this->assertTrue($zip2->open($tmp2));
        $manifest2 = $zip2->getFromName('waypost.json');
        $this->assertIsString($manifest2);
        $data2 = json_decode($manifest2, true);
        $this->assertIsArray($data2);
        $this->assertArrayNotHasKey('api_token', $data2);
        $zip2->close();
        unlink($tmp2);
    }
}
