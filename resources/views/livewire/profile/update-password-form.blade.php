<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        $user = Auth::user();

        $rules = [
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ];

        if ($user->password !== null) {
            $rules['current_password'] = ['required', 'string', 'current_password'];
        }

        try {
            $validated = $this->validate($rules);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-ink">
            {{ __('Update Password') }}
        </h2>

        <p class="mt-1 text-sm text-ink/70">
            @if (auth()->user()->password !== null)
                {{ __('Ensure your account is using a long, random password to stay secure.') }}
            @else
                {{ __('Add a password if you want to sign in with email as well as your social account.') }}
            @endif
        </p>
    </header>

    <form wire:submit="updatePassword" class="mt-6 space-y-6">
        @if (auth()->user()->password !== null)
            <div>
                <x-input-label for="update_password_current_password" :value="__('Current Password')" />
                <x-text-input wire:model="current_password" id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
                <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
            </div>
        @endif

        <div>
            <x-input-label for="update_password_password" :value="__('New Password')" />
            <x-text-input wire:model="password" id="update_password_password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
            <x-text-input wire:model="password_confirmation" id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            <x-action-message class="me-3" on="password-updated">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>
