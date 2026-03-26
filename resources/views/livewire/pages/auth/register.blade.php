<?php

use App\Models\User;
use Fleet\IdpClient\Support\FleetProvisioningRequest;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $plainPassword = $validated['password'];
        FleetProvisioningRequest::stashPasswordForRegisteredEvent($plainPassword);
        $validated['password'] = Hash::make($plainPassword);

        $user = User::create($validated);

        event(new Registered($user));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-ink">{{ __('Create account') }}</h1>
        <p class="mt-1 text-sm text-ink/70">{{ __('Start organizing projects on :app.', ['app' => config('app.name')]) }}</p>
    </div>

    @if (session('oauth_error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800" role="alert">
            {{ session('oauth_error') }}
        </div>
    @endif

    <x-fleet-idp::social-login-buttons class="mb-6" variant="waypost" />

    @if (\Fleet\IdpClient\View\Components\SocialLoginButtons::isEnabled())
        <div class="relative mb-6">
            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t border-cream-300"></div>
            </div>
            <div class="relative flex justify-center text-xs uppercase tracking-wide">
                <span class="bg-cream-50 px-3 text-ink/55">{{ __('Or with email') }}</span>
            </div>
        </div>
    @endif

    <form wire:submit="register" class="space-y-4">
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" name="name" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input wire:model="password" id="password" class="block mt-1 w-full"
                type="password"
                name="password"
                required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full"
                type="password"
                name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:items-center sm:justify-end">
            <a class="text-center text-sm font-medium text-ink/70 hover:text-ink sm:me-auto sm:text-start" href="{{ route('login') }}" wire:navigate>
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="w-full justify-center sm:w-auto">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</div>
