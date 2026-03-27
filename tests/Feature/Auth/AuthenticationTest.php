<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        FleetSocialLoginPolicy::clearFake();

        parent::tearDown();
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSeeLivewire('pages.auth.login')
            ->assertDontSee('Sign in without your password')
            ->assertDontSee('Continue with email code or link')
            ->assertDontSee('Fleet IdP debug');
    }

    public function test_login_screen_shows_fleet_debug_panel_when_social_policy_debug_enabled(): void
    {
        config(['fleet_idp.socialite.debug_policy_fetch' => true]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('Fleet IdP debug', false)
            ->assertSee('resolved_policy_flags', false);
    }

    public function test_login_screen_shows_fleet_debug_panel_when_idp_policy_requests_debug(): void
    {
        config(['fleet_idp.socialite.debug_policy_fetch' => false]);

        FleetSocialLoginPolicy::fake([
            'satellite_policy_debug' => true,
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('Fleet IdP debug', false)
            ->assertSee('debug_from_idp_policy', false);
    }

    public function test_login_screen_shows_passwordless_card_when_enabled_without_fleet(): void
    {
        FleetSocialLoginPolicy::fake([
            'guest_email_login_card_without_idp_delivery' => true,
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in without your password')
            ->assertSee('Continue to email sign-in');
    }

    public function test_login_screen_shows_passwordless_when_fleet_oauth_configured_and_policy_allows_magic_without_password_client(): void
    {
        config([
            'app.url' => 'https://waypost.test',
            'fleet_idp.url' => 'https://fleet-auth.test',
            'fleet_idp.client_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'fleet_idp.client_secret' => 'oauth-secret',
            'fleet_idp.password_client_id' => '',
            'fleet_idp.password_client_secret' => '',
        ]);

        FleetSocialLoginPolicy::fake([
            'email_login_magic_link' => true,
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in without your password')
            ->assertSee('Continue with magic sign-in link');
    }

    public function test_login_screen_shows_passwordless_card_when_always_show_guest_card_config_enabled(): void
    {
        config([
            'fleet_idp.email_sign_in.always_show_guest_card_on_login' => true,
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in without your password')
            ->assertSee('Continue to email sign-in');
    }

    public function test_login_screen_shows_fleet_oauth_when_providers_unreachable_and_optimistic_enabled(): void
    {
        config([
            'app.url' => 'https://waypost.test',
            'fleet_idp.url' => 'https://fleet-auth.test',
            'fleet_idp.client_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'fleet_idp.client_secret' => 'oauth-secret',
            'fleet_idp.socialite.policy_cache_seconds' => 0,
            'fleet_idp.socialite.optimistic_when_unreachable' => true,
        ]);

        Http::fake([
            'https://fleet-auth.test/api/social-login/providers*' => Http::response(['message' => 'no'], 503),
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('Continue with Fleet', false);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $component = Livewire::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password');

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $component = Livewire::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'wrong-password');

        $component->call('login');

        $component
            ->assertHasErrors()
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_navigation_menu_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response
            ->assertOk()
            ->assertSeeLivewire('layout.navigation');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Livewire::test('layout.navigation');

        $component->call('logout');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
