<?php

use App\Livewire\Forms\LoginForm;
use Fleet\IdpClient\FleetIdpPasswordGrant;
use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function mount(): void
    {
        if (rtrim((string) config('fleet_idp.url', ''), '/') !== '') {
            FleetSocialLoginPolicy::snapshot();
        }
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->ensureIsNotRateLimited();

        $email = (string) $this->form->email;
        $password = (string) $this->form->password;

        if (FleetIdpPasswordGrant::isConfigured()) {
            $user = FleetIdpPasswordGrant::attempt($email, $password);
            if ($user === null) {
                RateLimiter::hit($this->form->throttleKey());

                throw ValidationException::withMessages([
                    'form.email' => trans('auth.failed'),
                ]);
            }

            if ($user->hasTwoFactorEnabled() && FleetSocialLoginPolicy::respectLocalTotpForSessions()) {
                Session::put([
                    'two_factor.id' => $user->id,
                    'two_factor.remember' => $this->form->remember,
                ]);
                Session::regenerateToken();
                RateLimiter::clear($this->form->throttleKey());

                $this->redirect(route('two-factor.challenge', absolute: false), navigate: false);

                return;
            }

            Auth::login($user, $this->form->remember);
            RateLimiter::clear($this->form->throttleKey());
            Session::regenerate();
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        if (! Auth::attempt($this->form->only(['email', 'password']), $this->form->remember)) {
            RateLimiter::hit($this->form->throttleKey());

            throw ValidationException::withMessages([
                'form.email' => trans('auth.failed'),
            ]);
        }

        $user = Auth::user();
        assert($user !== null);

        if ($user->hasTwoFactorEnabled() && FleetSocialLoginPolicy::respectLocalTotpForSessions()) {
            Auth::logout();

            Session::put([
                'two_factor.id' => $user->id,
                'two_factor.remember' => $this->form->remember,
            ]);
            Session::regenerateToken();
            RateLimiter::clear($this->form->throttleKey());

            $this->redirect(route('two-factor.challenge', absolute: false), navigate: false);

            return;
        }

        RateLimiter::clear($this->form->throttleKey());

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-ink">{{ __('Log in') }}</h1>
        <p class="mt-1 text-sm text-ink/70">{{ __('Welcome back to :app.', ['app' => config('app.name')]) }}</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    {{-- Fleet OAuth button, dividers, passwordless card: vendor/shaferllc/fleet-idp-client/.../login-screen-fleet-surfaces.blade.php --}}
    <x-fleet-idp::login-screen-fleet-surfaces variant="waypost" :wire-navigate="true" />

    <form wire:submit="login" class="space-y-4">
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="form.email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input wire:model="form.password" id="password" class="block mt-1 w-full"
                type="password"
                name="password"
                required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <div class="flex items-center">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-cream-300 text-sage shadow-sm focus:ring-sage" name="remember">
                <span class="ms-2 text-sm text-ink/70">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:items-center sm:justify-end">
            @if (Route::has('password.request'))
                <a class="text-center text-sm font-medium text-sage-dark hover:text-sage-deeper sm:me-auto sm:text-start" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="w-full justify-center sm:w-auto">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>

    @if (Route::has('register'))
        <p class="mt-8 text-center text-sm text-ink/70">
            {{ __('No account yet?') }}
            <a href="{{ route('register') }}" wire:navigate class="font-semibold text-sage-dark hover:text-sage-deeper">{{ __('Create one') }}</a>
        </p>
        <x-fleet-idp::login-screen-fleet-register-footnotes />
    @endif
</div>
