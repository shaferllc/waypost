<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Fleet\IdpClient\Testing\InteractsWithFleetIdpPasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use InteractsWithFleetIdpPasswordReset;
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee(__('Forgot password'), false);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_success_page_does_not_repeat_long_intro(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->from('/forgot-password')
            ->followingRedirects()
            ->post('/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertSee(trans('fleet-idp::account.reset_link_sent'), false)
            ->assertDontSee('Enter your email and', false);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $response = $this->get('/reset-password/'.$notification->token.'?email='.urlencode($user->email));

            $response->assertOk()->assertSee(__('Reset password'), false);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this
                ->post('/reset-password', [
                    'token' => $notification->token,
                    'email' => $user->email,
                    'password' => 'new-password-123',
                    'password_confirmation' => 'new-password-123',
                ])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_forgot_password_for_fleet_linked_user_requests_reset_via_fleet_api(): void
    {
        Notification::fake();

        $this->configureFleetIdpWithProvisioningLookup();
        $this->fakeFleetProvisioningPasswordReset(true);

        $user = User::factory()->create([
            'provider' => 'fleet_auth',
            'provider_id' => 'fleet-user-1',
        ]);

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('fleet_idp_fleet_reset_confirm')
            ->assertSessionMissing('status');

        Notification::assertNothingSent();

        Http::assertNothingSent();

        $this->from('/forgot-password')
            ->post(route('password.email.fleet'), [])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status')
            ->assertSessionMissing('fleet_idp_fleet_reset_confirm')
            ->assertSessionMissing('fleet_idp_pending_fleet_reset');

        Notification::assertNothingSent();

        Http::assertSent(function ($request) use ($user) {
            return str_ends_with($request->url(), '/api/provisioning/users/password-reset')
                && $request->header('Authorization')[0] === 'Bearer provision-secret'
                && $request['email'] === $user->email;
        });
    }

    public function test_forgot_password_for_fleet_linked_user_uses_local_broker_when_fleet_api_fails(): void
    {
        Notification::fake();

        $this->configureFleetIdpWithProvisioningLookup();
        $this->fakeFleetProvisioningPasswordReset(false);

        $user = User::factory()->create([
            'provider' => 'fleet_auth',
            'provider_id' => 'fleet-user-1',
        ]);

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('fleet_idp_fleet_reset_confirm');

        $this->from('/forgot-password')
            ->post(route('password.email.fleet'), [])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status')
            ->assertSessionMissing('fleet_idp_pending_fleet_reset');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_unknown_email_requests_fleet_reset_when_lookup_succeeds(): void
    {
        Notification::fake();

        $this->configureFleetIdpWithProvisioningLookup();

        $base = rtrim((string) config('fleet_idp.url'), '/');
        Http::fake([
            $base.'/api/provisioning/users/lookup' => Http::response(['exists' => true], 200),
            $base.'/api/provisioning/users/password-reset' => Http::response(['status' => 'accepted'], 200),
        ]);

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHas('fleet_idp_fleet_reset_confirm')
            ->assertSessionMissing('status');

        Notification::assertNothingSent();

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/api/provisioning/users/lookup')
                && $request['email'] === 'nobody@example.com';
        });
        Http::assertNotSent(function ($request) {
            return str_ends_with($request->url(), '/api/provisioning/users/password-reset');
        });

        $this->from('/forgot-password')
            ->post(route('password.email.fleet'), [])
            ->assertSessionHas('status')
            ->assertSessionMissing('fleet_idp_pending_fleet_reset');

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/api/provisioning/users/password-reset')
                && $request['email'] === 'nobody@example.com';
        });
    }

    public function test_forgot_password_unknown_email_is_ambiguous_when_fleet_lookup_says_missing(): void
    {
        Notification::fake();

        $this->configureFleetIdpWithProvisioningLookup();
        $this->fakeFleetProvisioningUserLookup(false);

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionMissing('fleet_idp_pending_fleet_reset')
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_forgot_password_unknown_email_is_ambiguous_when_fleet_lookup_errors(): void
    {
        Notification::fake();

        $this->configureFleetIdpWithProvisioningLookup();

        $base = rtrim((string) config('fleet_idp.url'), '/');
        Http::fake([
            $base.'/api/provisioning/users/lookup' => Http::response(['message' => 'server error'], 500),
        ]);

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status')
            ->assertSessionMissing('fleet_idp_fleet_reset_confirm');

        Notification::assertNothingSent();
    }

    public function test_forgot_password_fleet_style_local_user_gets_fleet_confirm_step(): void
    {
        Notification::fake();

        $this->configureFleetIdpWithProvisioningLookup();
        config(['fleet_idp.account.likely_email_domains' => ['fleet.test']]);

        $user = User::factory()->create([
            'email' => 'admin@fleet.test',
            'provider' => null,
            'provider_id' => null,
        ]);

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('fleet_idp_fleet_reset_confirm')
            ->assertSessionMissing('status');

        $this->assertSame('likely_domain', session('fleet_idp_fleet_reset_confirm.prompt'));

        Notification::assertNothingSent();
    }

    public function test_forgot_password_fleet_style_unknown_email_gets_fleet_confirm_when_lookup_says_missing(): void
    {
        Notification::fake();

        $this->configureFleetIdpWithProvisioningLookup();
        $this->fakeFleetProvisioningUserLookup(false);
        config(['fleet_idp.account.likely_email_domains' => ['fleet.test']]);

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'admin@fleet.test'])
            ->assertSessionHas('fleet_idp_fleet_reset_confirm')
            ->assertSessionMissing('status');

        $this->assertSame('likely_domain', session('fleet_idp_fleet_reset_confirm.prompt'));

        Notification::assertNothingSent();
    }

    public function test_forgot_password_fleet_style_domain_works_without_provisioning_token(): void
    {
        Notification::fake();

        config([
            'fleet_idp.url' => 'https://fleet.example.test',
            'fleet_idp.account.local_password_only' => false,
            'fleet_idp.provisioning.token' => '',
            'fleet_idp.account.likely_email_domains' => ['fleet.test'],
        ]);

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'admin@fleet.test'])
            ->assertSessionHas('fleet_idp_fleet_reset_confirm');

        $this->assertSame('likely_domain', session('fleet_idp_fleet_reset_confirm.prompt'));

        Notification::assertNothingSent();
    }

    public function test_forgot_password_fleet_confirm_survives_intermediate_get_like_a_browser(): void
    {
        Notification::fake();

        $this->configureFleetIdpWithProvisioningLookup();
        $this->fakeFleetProvisioningPasswordReset(true);

        $user = User::factory()->create([
            'provider' => 'fleet_auth',
            'provider_id' => 'fleet-user-1',
        ]);

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect();

        $this->get('/forgot-password')
            ->assertOk()
            ->assertSessionHas('fleet_idp_fleet_reset_confirm');

        $this->from('/forgot-password')
            ->post(route('password.email.fleet'), [])
            ->assertSessionHas('status')
            ->assertSessionMissing('fleet_idp_fleet_reset_confirm');

        Http::assertSent(function ($request) use ($user) {
            return str_ends_with($request->url(), '/api/provisioning/users/password-reset')
                && $request['email'] === $user->email;
        });
    }
}
