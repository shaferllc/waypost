<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginEmailCodePageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        FleetSocialLoginPolicy::clearFake();

        parent::tearDown();
    }

    public function test_email_code_login_page_renders_without_fleet_password_grant(): void
    {
        config([
            'fleet_idp.url' => '',
            'fleet_idp.password_client_id' => '',
            'fleet_idp.password_client_secret' => '',
        ]);

        $this->get(route('login.email-code'))
            ->assertOk()
            ->assertSeeVolt('pages.auth.login-email-code');
    }

    public function test_email_code_login_page_renders_when_configured_and_policy_allows(): void
    {
        config([
            'fleet_idp.url' => 'http://fleet-auth.test',
            'fleet_idp.password_client_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'fleet_idp.password_client_secret' => 'secret',
            'fleet_idp.user_model' => User::class,
        ]);

        FleetSocialLoginPolicy::fake([
            'github' => false,
            'google' => false,
            'require_two_factor' => false,
            'email_login_code' => true,
            'email_login_magic_link' => true,
        ]);

        $this->get(route('login.email-code'))
            ->assertOk()
            ->assertSeeVolt('pages.auth.login-email-code');
    }
}
