<?php

namespace Tests\Feature;

use App\Models\User;
use Fleet\IdpClient\Notifications\ConfirmProfileEmailCodeSignInNotification;
use Fleet\IdpClient\Support\ProfileEmailSignInConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response
            ->assertOk()
            ->assertSeeLivewire('profile.update-profile-information-form')
            ->assertSeeLivewire('profile.update-password-form')
            ->assertSeeLivewire('profile.api-tokens-form')
            ->assertSeeLivewire('profile.two-factor-authentication-form')
            ->assertSeeLivewire('profile.email-code-sign-in-form')
            ->assertSeeLivewire('profile.delete-user-form');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Livewire::test('profile.update-profile-information-form')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->call('updateProfileInformation');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Livewire::test('profile.update-profile-information-form')
            ->set('name', 'Test User')
            ->set('email', $user->email)
            ->call('updateProfileInformation');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Livewire::test('profile.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Livewire::test('profile.delete-user-form')
            ->set('password', 'wrong-password')
            ->call('deleteUser');

        $component
            ->assertHasErrors('password')
            ->assertNoRedirect();

        $this->assertNotNull($user->fresh());
    }

    public function test_local_user_profile_hides_email_code_toggle_when_fleet_policy_is_magic_only(): void
    {
        config([
            'fleet_idp.url' => 'https://fleet.test',
            'fleet_idp.client_id' => 'oauth-client-id',
            'fleet_idp.password_client_id' => 'password-client-id',
            'fleet_idp.password_client_secret' => 'password-client-secret',
            'fleet_idp.socialite.policy_cache_seconds' => 0,
        ]);

        Http::fake([
            'https://fleet.test/api/social-login/providers*' => Http::response([
                'github' => false,
                'google' => false,
                'allow_two_factor' => true,
                'require_two_factor' => false,
                'require_email_verification' => false,
                'email_login_code' => false,
                'email_login_magic_link' => true,
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertDontSee('Turn on code', false)
            ->assertSee('Turn on link', false);
    }

    public function test_user_can_enable_email_code_sign_in_from_profile(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->assertFalse($user->email_login_code_enabled);
        $this->assertFalse($user->email_login_magic_link_enabled);

        $this->actingAs($user);

        Livewire::test('profile.email-code-sign-in-form')
            ->set('current_password', 'password')
            ->call('enableCode')
            ->assertHasNoErrors();

        $fresh = $user->fresh();
        $this->assertFalse($fresh->email_login_code_enabled);
        $this->assertNotNull($fresh->email_code_sign_in_pending_token_hash);
        $this->assertFalse($fresh->email_login_magic_link_enabled);

        Notification::assertSentTo($user, ConfirmProfileEmailCodeSignInNotification::class);
    }

    public function test_enabling_email_code_clears_magic_when_mutually_exclusive(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_login_magic_link_enabled' => true,
            'email_login_code_enabled' => false,
        ]);

        ProfileEmailSignInConfirmation::sendEmailCodeConfirmationMail($user);

        $fresh = $user->fresh();
        $this->assertFalse($fresh->email_login_magic_link_enabled);
        $this->assertNotNull($fresh->email_code_sign_in_pending_token_hash);
    }

    public function test_user_can_disable_email_code_after_confirming_password_in_modal(): void
    {
        $user = User::factory()->create([
            'email_login_code_enabled' => true,
        ]);

        $this->actingAs($user);

        Livewire::test('profile.email-code-sign-in-form')
            ->set('confirmDisableCodeModal', true)
            ->set('current_password', 'password')
            ->call('disableCode')
            ->assertSet('confirmDisableCodeModal', false)
            ->assertHasNoErrors();

        $this->assertFalse($user->fresh()->email_login_code_enabled);
    }

    public function test_profile_shows_fleet_account_link_when_fleet_oauth_and_provisioning_configured(): void
    {
        config([
            'fleet_idp.url' => 'https://fleet.test',
            'fleet_idp.client_id' => 'client-id',
            'fleet_idp.client_secret' => 'client-secret',
            'fleet_idp.provisioning.token' => 'provision-token',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSeeLivewire('profile.fleet-account-link-form');
    }

    public function test_user_can_sync_password_to_fleet_from_profile(): void
    {
        Http::fake([
            'https://fleet.test/api/provisioning/users' => Http::response(['status' => 'created'], 201),
        ]);

        config([
            'fleet_idp.url' => 'https://fleet.test',
            'fleet_idp.client_id' => 'client-id',
            'fleet_idp.client_secret' => 'client-secret',
            'fleet_idp.provisioning.token' => 'provision-token',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test('profile.fleet-account-link-form')
            ->set('current_password', 'password')
            ->call('syncToFleet')
            ->assertHasNoErrors()
            ->assertRedirect(route('profile'));

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://fleet.test/api/provisioning/users'
                && $request->hasHeader('Authorization', 'Bearer provision-token')
                && $request['email'] !== null;
        });
    }

    public function test_oauth_only_user_can_delete_account_with_delete_confirmation(): void
    {
        $user = User::factory()->create([
            'password' => null,
            'provider' => 'github',
            'provider_id' => '12345',
        ]);

        $this->actingAs($user);

        $component = Livewire::test('profile.delete-user-form')
            ->set('delete_confirmation', 'DELETE')
            ->call('deleteUser');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }
}
