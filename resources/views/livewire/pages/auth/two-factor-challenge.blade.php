<?php

use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $code = '';

    public bool $useRecovery = false;

    public function verify(TwoFactorAuthenticationService $twoFactor): void
    {
        $rules = $this->useRecovery
            ? ['code' => ['required', 'string', 'max:32']]
            : ['code' => ['required', 'string', 'size:6']];

        $this->validate($rules);

        $id = Session::get('two_factor.id');
        if (! is_int($id) && ! is_string($id)) {
            Session::forget(['two_factor.id', 'two_factor.remember']);

            $this->redirect(route('login', absolute: false), navigate: false);

            return;
        }

        $user = User::query()->find($id);
        if (! $user instanceof User) {
            Session::forget(['two_factor.id', 'two_factor.remember']);

            $this->redirect(route('login', absolute: false), navigate: false);

            return;
        }

        $rateKey = 'two-factor-challenge:'.Session::id();

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);

            throw ValidationException::withMessages([
                'code' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        $valid = $this->useRecovery
            ? $twoFactor->verifyRecoveryCode($user, $this->code)
            : $twoFactor->verify($user, $this->code);

        if (! $valid) {
            RateLimiter::hit($rateKey, 60);

            throw ValidationException::withMessages([
                'code' => __('Invalid authentication code.'),
            ]);
        }

        RateLimiter::clear($rateKey);

        $remember = (bool) Session::pull('two_factor.remember', false);
        Session::forget('two_factor.id');

        Auth::login($user, $remember);

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: false);
    }
}; ?>

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-ink">{{ __('Two-factor authentication') }}</h1>
        <p class="mt-1 text-sm text-ink/70">
            @if ($useRecovery)
                {{ __('Enter one of your recovery codes.') }}
            @else
                {{ __('Open your authenticator app and enter the 6-digit code for :app.', ['app' => config('app.name')]) }}
            @endif
        </p>
    </div>

    <form wire:submit="verify" class="space-y-4">
        <div>
            <x-input-label for="code" :value="$useRecovery ? __('Recovery code') : __('Code')" />
            <x-text-input
                wire:model="code"
                id="code"
                class="mt-1 block w-full tracking-widest"
                type="text"
                name="code"
                required
                autofocus
                :autocomplete="$useRecovery ? 'one-time-code' : 'one-time-code'"
                :placeholder="$useRecovery ? 'XXXX-XXXX' : '000000'"
            />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <button
                type="button"
                wire:click="$toggle('useRecovery'); $set('code', '')"
                class="text-sm font-medium text-sage-dark hover:text-sage-deeper"
            >
                @if ($useRecovery)
                    {{ __('Use authenticator code') }}
                @else
                    {{ __('Use a recovery code') }}
                @endif
            </button>

            <x-primary-button class="w-full justify-center sm:w-auto">
                {{ __('Continue') }}
            </x-primary-button>
        </div>
    </form>

    <p class="mt-8 text-center text-sm text-ink/70">
        <a href="{{ route('login') }}" class="font-semibold text-sage-dark hover:text-sage-deeper">{{ __('Back to log in') }}</a>
    </p>
</div>
