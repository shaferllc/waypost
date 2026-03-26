<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Fleet\IdpClient\Notifications\ConfirmProfileEmailCodeSignInNotification;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ConfirmEmailCodeSignInTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabling_code_sends_confirmation_and_does_not_activate_until_link_clicked(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test('profile.email-code-sign-in-form')
            ->set('current_password', 'password')
            ->call('enableCode')
            ->assertHasNoErrors();

        $this->assertFalse($user->fresh()->email_login_code_enabled);
        $this->assertNotNull($user->fresh()->email_code_sign_in_pending_token_hash);

        Notification::assertSentTo($user, ConfirmProfileEmailCodeSignInNotification::class);
    }

    public function test_confirmation_link_shows_interstitial_then_post_enables_code_sign_in(): void
    {
        $plain = str_repeat('a', 64);
        $user = User::factory()->create([
            'email_code_sign_in_pending_token_hash' => hash('sha256', $plain),
            'email_code_sign_in_pending_expires_at' => now()->addHour(),
            'email_login_code_enabled' => false,
        ]);

        $this->get(route('profile.confirm-email-code-sign-in', ['token' => $plain]))
            ->assertOk()
            ->assertSee(__('fleet-idp::email_sign_in.profile_confirm_page_button_code'), false);

        $this->assertFalse($user->fresh()->email_login_code_enabled);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post(route('profile.confirm-email-code-sign-in'), ['token' => $plain])
            ->assertRedirect()
            ->assertSessionHas('status');

        $fresh = $user->fresh();
        $this->assertTrue($fresh->email_login_code_enabled);
        $this->assertNull($fresh->email_code_sign_in_pending_token_hash);
    }

    public function test_invalid_confirmation_token_redirects_with_error(): void
    {
        $this->get(route('profile.confirm-email-code-sign-in', ['token' => str_repeat('b', 64)]))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_confirming_code_disables_magic_when_mutually_exclusive(): void
    {
        $plain = str_repeat('c', 64);
        $user = User::factory()->create([
            'email_login_magic_link_enabled' => true,
            'email_login_code_enabled' => false,
            'email_code_sign_in_pending_token_hash' => hash('sha256', $plain),
            'email_code_sign_in_pending_expires_at' => now()->addHour(),
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post(route('profile.confirm-email-code-sign-in'), ['token' => $plain])
            ->assertRedirect()
            ->assertSessionHas('status');

        $fresh = $user->fresh();
        $this->assertTrue($fresh->email_login_code_enabled);
        $this->assertFalse($fresh->email_login_magic_link_enabled);
    }
}
