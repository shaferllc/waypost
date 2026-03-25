<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectWishlistApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_index_requires_authentication(): void
    {
        $this->getJson('/api/projects')
            ->assertUnauthorized();
    }

    public function test_projects_index_returns_user_projects(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Alpha',
            'description' => 'A',
        ]);
        Project::query()->create([
            'user_id' => $other->id,
            'name' => 'Other',
            'description' => 'B',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/projects');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alpha');
    }

    public function test_store_wishlist_item_requires_authentication(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Mine',
        ]);

        $this->postJson("/api/projects/{$project->id}/wishlist-items", [
            'title' => 'Idea',
        ])->assertUnauthorized();
    }

    public function test_store_wishlist_item_creates_record(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Mine',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/projects/{$project->id}/wishlist-items", [
            'title' => 'From API',
            'notes' => 'https://example.com/page',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'From API')
            ->assertJsonPath('data.notes', 'https://example.com/page');

        $this->assertDatabaseHas('wishlist_items', [
            'project_id' => $project->id,
            'title' => 'From API',
            'notes' => 'https://example.com/page',
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('project_activities', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'action' => 'wishlist_item.created',
        ]);
    }

    public function test_store_wishlist_item_appends_sort_order_after_existing(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Mine',
        ]);
        WishlistItem::query()->create([
            'project_id' => $project->id,
            'title' => 'First',
            'sort_order' => 5,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/projects/{$project->id}/wishlist-items", [
            'title' => 'Second',
        ])->assertCreated()
            ->assertJsonPath('data.sort_order', 6);
    }

    public function test_store_wishlist_item_forbidden_for_other_users_project(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $owner->id,
            'name' => 'Private',
        ]);

        Sanctum::actingAs($intruder);

        $this->postJson("/api/projects/{$project->id}/wishlist-items", [
            'title' => 'Hack',
        ])->assertForbidden();
    }

    public function test_bearer_token_authenticates_request(): void
    {
        $user = User::factory()->create();
        $project = Project::query()->create([
            'user_id' => $user->id,
            'name' => 'Mine',
        ]);
        $plain = $user->createToken('test')->plainTextToken;

        $this->withToken($plain)
            ->postJson("/api/projects/{$project->id}/wishlist-items", [
                'title' => 'Via bearer',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('wishlist_items', [
            'project_id' => $project->id,
            'title' => 'Via bearer',
        ]);
    }
}
