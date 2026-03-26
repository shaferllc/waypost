<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        FleetSocialLoginPolicy::clearFake();

        parent::tearDown();
    }

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response
            ->assertSeeVolt('pages.auth.verify-email')
            ->assertStatus(200);
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_unverified_user_can_visit_dashboard_when_satellite_policy_does_not_require_email_verification(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_unverified_user_is_redirected_from_dashboard_when_satellite_policy_requires_email_verification(): void
    {
        FleetSocialLoginPolicy::fake([
            'github' => true,
            'google' => true,
            'require_two_factor' => false,
            'require_email_verification' => true,
            'email_login_code' => false,
            'email_login_magic_link' => false,
        ]);

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice', absolute: false));
    }

    public function test_api_returns_403_for_unverified_user_when_satellite_policy_requires_email_verification(): void
    {
        FleetSocialLoginPolicy::fake([
            'github' => true,
            'google' => true,
            'require_two_factor' => false,
            'require_email_verification' => true,
            'email_login_code' => false,
            'email_login_magic_link' => false,
        ]);

        $user = User::factory()->unverified()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/projects')
            ->assertForbidden();
    }
}
