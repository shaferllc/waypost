<?php

use App\Livewire\Forms\LoginForm;
use Fleet\IdpClient\FleetIdpOAuth;
use Fleet\IdpClient\FleetIdpPasswordGrant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

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

            if ($user->hasTwoFactorEnabled()) {
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

        if ($user->hasTwoFactorEnabled()) {
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
        @if (FleetIdpPasswordGrant::isConfigured())
            <p class="mt-3 text-xs leading-relaxed text-ink/55">
                {{ __('Your Waypost profile is synced from Fleet. Sign in with the same email and password you use there (or use Fleet sign-in).') }}
            </p>
        @elseif (FleetIdpOAuth::isConfigured())
            <p class="mt-3 text-xs leading-relaxed text-ink/55">
                {{ __('Use Fleet sign-in to create your session from the central auth site. Email login below uses your local Waypost password unless password sync is configured.') }}
            </p>
        @endif
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if (session('oauth_error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800" role="alert">
            {{ session('oauth_error') }}
        </div>
    @endif

    <x-oauth-providers class="mb-6" />

    @if (\App\View\Components\OauthProviders::isEnabled())
        <div class="relative mb-6">
            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t border-cream-300"></div>
            </div>
            <div class="relative flex justify-center text-xs uppercase tracking-wide">
                <span class="bg-cream-50 px-3 text-ink/55">{{ __('Or sign in with email') }}</span>
            </div>
        </div>
    @endif

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
        @if (filled(config('fleet_idp.provisioning.token')))
            <p class="mt-2 text-center text-xs text-ink/50">
                {{ __('New here? Register below—your account is also created in Fleet Auth automatically.') }}
            </p>
        @elseif (FleetIdpOAuth::isConfigured() || FleetIdpPasswordGrant::isConfigured())
            <p class="mt-2 text-center text-xs text-ink/50">
                {{ __('New users: register in Fleet first, then sign in here.') }}
            </p>
        @endif
    @endif
</div>
