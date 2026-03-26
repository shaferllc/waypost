<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.update-password-form')
            ->set('current_password', 'password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.update-password-form')
            ->set('current_password', 'wrong-password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword');

        $component
            ->assertHasErrors(['current_password'])
            ->assertNoRedirect();
    }

    public function test_oauth_user_can_set_initial_password_without_current_password(): void
    {
        $user = User::factory()->create([
            'password' => null,
            'provider' => 'google',
            'provider_id' => 'sub-abc',
        ]);

        $this->actingAs($user);

        $component = Volt::test('profile.update-password-form')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_fleet_linked_user_updates_password_via_fleet_provisioning_api(): void
    {
        config([
            'fleet_idp.url' => 'https://fleet.test',
            'fleet_idp.provisioning.token' => 'provisioning-secret',
            'fleet_idp.account.local_password_only' => false,
        ]);

        Http::fake([
            'https://fleet.test/api/provisioning/users/password-change' => Http::response(['status' => 'updated'], 200),
        ]);

        $user = User::factory()->create([
            'provider' => 'fleet_auth',
            'provider_id' => 'sub-fleet-1',
            'password' => Hash::make('old-local'),
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-password-form')
            ->set('current_password', 'current-plain')
            ->set('password', 'new-password-99')
            ->set('password_confirmation', 'new-password-99')
            ->call('updatePassword')
            ->assertHasNoErrors();

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->url() === 'https://fleet.test/api/provisioning/users/password-change'
                && ($data['email'] ?? null) !== null
                && ($data['current_password'] ?? null) === 'current-plain'
                && ($data['password'] ?? null) === 'new-password-99';
        });

        $this->assertTrue(Hash::check('new-password-99', $user->refresh()->password));
    }

    public function test_fleet_linked_user_sees_validation_errors_from_fleet_api(): void
    {
        config([
            'fleet_idp.url' => 'https://fleet.test',
            'fleet_idp.provisioning.token' => 'provisioning-secret',
            'fleet_idp.account.local_password_only' => false,
        ]);

        Http::fake([
            'https://fleet.test/api/provisioning/users/password-change' => Http::response([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'current_password' => ['The provided password does not match your current password.'],
                ],
            ], 422),
        ]);

        $user = User::factory()->create([
            'provider' => 'fleet_auth',
            'provider_id' => 'sub-fleet-2',
            'password' => Hash::make('local'),
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-password-form')
            ->set('current_password', 'wrong')
            ->set('password', 'new-password-99')
            ->set('password_confirmation', 'new-password-99')
            ->call('updatePassword')
            ->assertHasErrors(['current_password']);
    }
}
