<?php

use Fleet\IdpClient\FleetEmailSignIn;
use Fleet\IdpClient\FleetEmailSignInSession;
use Fleet\IdpClient\Support\EmailSignInUserOptions;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    public string $code = '';

    public string $delivery = 'code';

    public bool $offerCode = true;

    public bool $offerMagic = true;

    public bool $sentCode = false;

    public bool $remember = true;

    public function mount(): void
    {
        $this->offerCode = FleetEmailSignIn::loginPageOffersCode();
        $this->offerMagic = FleetEmailSignIn::loginPageOffersMagicLink();

        if ($this->offerCode && ! $this->offerMagic) {
            $this->delivery = 'code';
        } elseif (! $this->offerCode && $this->offerMagic) {
            $this->delivery = 'magic_link';
        }
    }

    /**
     * @return list<string>
     */
    private function allowedDeliveries(): array
    {
        return array_values(array_filter([
            $this->offerCode ? 'code' : null,
            $this->offerMagic ? 'magic_link' : null,
        ]));
    }

    public function send(): void
    {
        $allowed = $this->allowedDeliveries();
        if ($allowed === []) {
            throw ValidationException::withMessages([
                'email' => __('Email sign-in is not available for this app. Use password login or contact support.'),
            ]);
        }

        $this->validate([
            'email' => ['required', 'string', 'email'],
            'delivery' => ['required', Rule::in($allowed)],
        ]);

        $key = 'email-login-send:'.Str::lower($this->email).'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => __('Too many attempts. Please try again in :seconds seconds.', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }

        RateLimiter::hit($key, 3600);

        $result = FleetEmailSignIn::send($this->email, $this->delivery);
        if (! $result['ok']) {
            throw ValidationException::withMessages([
                'email' => $result['error'] ?? __('Could not send a sign-in message.'),
            ]);
        }

        if ($this->delivery === 'magic_link') {
            session()->flash('status', __('If an account exists with email sign-in turned on, check your inbox for the link.'));

            return;
        }

        $this->sentCode = true;
        session()->flash('status', __('If an account exists with email sign-in turned on, we sent a six-digit code.'));
    }

    public function verify(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ]);

        $key = 'email-login-verify:'.Str::lower($this->email).'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages([
                'code' => __('Too many attempts. Please try again in :seconds seconds.', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }

        RateLimiter::hit($key, 3600);

        $user = FleetEmailSignIn::verifyCode($this->email, $this->code);
        if ($user === null) {
            throw ValidationException::withMessages([
                'code' => __('Invalid or expired code.'),
            ]);
        }

        if (! EmailSignInUserOptions::userAllowsCode($user)) {
            throw ValidationException::withMessages([
                'code' => __('Invalid or expired code.'),
            ]);
        }

        RateLimiter::clear($key);
        RateLimiter::clear('email-login-send:'.Str::lower($this->email).'|'.request()->ip());

        $next = FleetEmailSignInSession::complete($user, $this->remember);

        if ($next['mode'] === 'two_factor') {
            $this->redirect($next['url'], navigate: false);

            return;
        }

        $this->redirectIntended(default: $next['url'], navigate: true);
    }
}; ?>

<div>
    <div class="mb-8">
        <h1 class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">{{ __('Sign in with email') }}</h1>
        <p class="mt-2 text-sm leading-relaxed text-ink/60">
            @if ($offerCode && $offerMagic)
                {{ __('Enter your email and choose a one-time code or magic link. Turn on the options you want under Profile first.') }}
            @elseif ($offerMagic)
                {{ __('Enter your email to receive a magic sign-in link. Turn this on under Profile first.') }}
            @elseif ($offerCode)
                {{ __('Enter your email to receive a one-time code. Turn this on under Profile first.') }}
            @else
                {{ __('Email sign-in is not available.') }}
            @endif
        </p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if ($offerCode && $offerMagic)
        <div class="mb-4 space-y-2">
            <p class="text-xs font-medium uppercase tracking-wide text-ink/55">{{ __('Choose one') }}</p>
            <label class="flex items-center gap-2 text-sm text-ink/80">
                <input type="radio" wire:model.live="delivery" value="code" class="border-cream-300 text-sage focus:ring-sage" />
                {{ __('One-time code') }}
            </label>
            <label class="flex items-center gap-2 text-sm text-ink/80">
                <input type="radio" wire:model.live="delivery" value="magic_link" class="border-cream-300 text-sage focus:ring-sage" />
                {{ __('Magic link') }}
            </label>
        </div>
    @endif

    @if (! $offerCode && ! $offerMagic)
        <p class="text-sm text-ink/65">{{ __('Use password login or contact support.') }}</p>
        <div class="pt-4">
            <a href="{{ route('login') }}" wire:navigate class="text-sm font-medium text-sage-dark hover:text-sage-deeper">{{ __('Back to password login') }}</a>
        </div>
    @else
    <form wire:submit="send" class="space-y-4">
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="remember" id="remember" type="checkbox" class="rounded border-cream-300 text-sage shadow-sm focus:ring-sage" />
                <span class="ms-2 text-sm text-ink/70">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
            <a href="{{ route('login') }}" wire:navigate class="text-center text-sm font-medium text-sage-dark hover:text-sage-deeper sm:me-auto sm:text-start">{{ __('Back to password login') }}</a>
            <x-primary-button type="submit" class="w-full justify-center sm:w-auto">
                @if ($delivery === 'magic_link')
                    {{ __('Email me a link') }}
                @else
                    {{ __('Send code') }}
                @endif
            </x-primary-button>
        </div>
    </form>

    @if ($sentCode)
        <div class="relative my-6">
            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t border-cream-300"></div>
            </div>
            <div class="relative flex justify-center text-xs uppercase tracking-wide">
                <span class="bg-white px-3 text-ink/55">{{ __('Enter code') }}</span>
            </div>
        </div>

        <form wire:submit="verify" class="space-y-4">
            <div>
                <x-input-label for="code" :value="__('Six-digit code')" />
                <x-text-input wire:model="code" id="code" class="block mt-1 w-full" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required />
                <x-input-error :messages="$errors->get('code')" class="mt-2" />
            </div>
            <div class="flex justify-end">
                <x-primary-button type="submit" class="w-full justify-center sm:w-auto">
                    {{ __('Verify and sign in') }}
                </x-primary-button>
            </div>
        </form>
    @endif
    @endif
</div>
