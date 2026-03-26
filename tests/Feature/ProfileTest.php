<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_user_can_enable_email_code_sign_in_from_profile(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->email_login_code_enabled);
        $this->assertFalse($user->email_login_magic_link_enabled);

        $this->actingAs($user);

        Livewire::test('profile.email-code-sign-in-form')
            ->set('current_password', 'password')
            ->call('enableCode')
            ->assertHasNoErrors();

        $fresh = $user->fresh();
        $this->assertTrue($fresh->email_login_code_enabled);
        $this->assertFalse($fresh->email_login_magic_link_enabled);
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
