<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_redirects_to_challenge_when_two_factor_enabled(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertRedirect(route('two-factor.challenge'));

        $this->assertGuest();
        $this->assertEquals($user->id, session('two_factor.id'));
    }

    public function test_two_factor_challenge_completes_login(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $code = $google2fa->getCurrentOtp($secret);

        $this->withSession([
            'two_factor.id' => $user->id,
            'two_factor.remember' => false,
        ]);

        Volt::test('pages.auth.two-factor-challenge')
            ->set('code', $code)
            ->call('verify')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }
}
