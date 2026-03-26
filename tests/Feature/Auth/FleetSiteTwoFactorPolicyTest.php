<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class FleetSiteTwoFactorPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        FleetSocialLoginPolicy::clearFake();

        parent::tearDown();
    }

    public function test_dashboard_redirects_to_profile_when_fleet_policy_requires_two_factor(): void
    {
        FleetSocialLoginPolicy::fake([
            'github' => true,
            'google' => true,
            'require_two_factor' => true,
            'email_login_code' => false,
            'email_login_magic_link' => false,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('profile'));

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk();
    }

    public function test_dashboard_reachable_when_user_satisfies_required_two_factor(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        FleetSocialLoginPolicy::fake([
            'github' => true,
            'google' => true,
            'require_two_factor' => true,
            'email_login_code' => false,
            'email_login_magic_link' => false,
        ]);

        $user = User::factory()->create([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }
}
