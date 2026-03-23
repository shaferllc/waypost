<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-ink">{{ __('Confirm password') }}</h1>
        <p class="mt-2 text-sm text-ink/70 leading-relaxed">
            {{ __('This is a secure area. Please confirm your password before continuing.') }}
        </p>
    </div>

    <form wire:submit="confirmPassword" class="space-y-4">
        <div>
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input wire:model="password"
                id="password"
                class="block mt-1 w-full"
                type="password"
                name="password"
                required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end pt-2">
            <x-primary-button class="w-full justify-center sm:w-auto">
                {{ __('Confirm') }}
            </x-primary-button>
        </div>
    </form>
</div>
