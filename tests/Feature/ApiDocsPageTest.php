<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_docs_requires_authentication(): void
    {
        $this->get(route('docs.api'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_api_docs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('docs.api'))
            ->assertOk()
            ->assertSee('Waypost HTTP API', false);
    }
}
