<?php

use App\Services\TwoFactorAuthenticationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';

    public string $email_confirm = '';

    public string $code = '';

    /** @var list<string> */
    public array $recoveryCodesToShow = [];

    public function beginSetup(TwoFactorAuthenticationService $twoFactor): void
    {
        $user = Auth::user();

        if ($user->hasTwoFactorEnabled() || $user->hasPendingTwoFactorSetup()) {
            return;
        }

        $this->validateCredentials($user);

        $secret = $twoFactor->generateSecretKey();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        $this->reset('current_password', 'email_confirm', 'code');
        $this->recoveryCodesToShow = [];
    }

    public function confirmSetup(TwoFactorAuthenticationService $twoFactor): void
    {
        $user = Auth::user();
        if (! $user->hasPendingTwoFactorSetup()) {
            return;
        }

        $this->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        if (! $twoFactor->verify($user, $this->code)) {
            throw ValidationException::withMessages([
                'code' => __('Invalid code.'),
            ]);
        }

        $plainCodes = $twoFactor->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($plainCodes),
        ])->save();

        $this->recoveryCodesToShow = $plainCodes;
        $this->reset('code');
    }

    public function cancelSetup(): void
    {
        $user = Auth::user();
        if (! $user->hasPendingTwoFactorSetup()) {
            return;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        $this->reset('code', 'current_password', 'email_confirm');
        $this->recoveryCodesToShow = [];
    }

    public function disable(TwoFactorAuthenticationService $twoFactor): void
    {
        $user = Auth::user();
        if (! $user->hasTwoFactorEnabled()) {
            return;
        }

        $this->validateCredentials($user);

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        $this->reset('current_password', 'email_confirm', 'code');
        $this->recoveryCodesToShow = [];
    }

    public function regenerateRecoveryCodes(TwoFactorAuthenticationService $twoFactor): void
    {
        $user = Auth::user();
        if (! $user->hasTwoFactorEnabled()) {
            return;
        }

        $this->validateCredentials($user);

        $plainCodes = $twoFactor->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($plainCodes),
        ])->save();

        $this->recoveryCodesToShow = $plainCodes;
        $this->reset('current_password', 'email_confirm');
    }

    public function dismissRecoveryCodes(): void
    {
        $this->recoveryCodesToShow = [];
    }

    private function validateCredentials(\App\Models\User $user): void
    {
        if ($user->password !== null) {
            $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
            ]);
        } else {
            $this->validate([
                'email_confirm' => ['required', 'string', Rule::in([strtolower($user->email)])],
            ]);
        }
    }

}; ?>

@php
    $user = auth()->user();
    $twoFactor = app(\App\Services\TwoFactorAuthenticationService::class);
    $qrSvg = '';
    if ($user->hasPendingTwoFactorSetup()) {
        $qrSvg = $twoFactor->qrCodeSvg($twoFactor->otpauthUrl($user, $user->two_factor_secret));
    }
@endphp

<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-ink">
            {{ __('Two-factor authentication') }}
        </h2>

        <p class="mt-1 text-sm text-ink/70">
            {{ __('Add a second step after your password for stronger account security.') }}
        </p>
    </header>

    @if (count($recoveryCodesToShow) > 0)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-ink">
            <p class="font-semibold text-amber-900">{{ __('Save these recovery codes') }}</p>
            <p class="mt-1 text-amber-900/90">{{ __('Each code works once. Store them in a safe place.') }}</p>
            <ul class="mt-3 grid gap-1 font-mono text-sm">
                @foreach ($recoveryCodesToShow as $recoveryCode)
                    <li>{{ $recoveryCode }}</li>
                @endforeach
            </ul>
            <div class="mt-4">
                <x-secondary-button type="button" wire:click="dismissRecoveryCodes">
                    {{ __('I have saved them') }}
                </x-secondary-button>
            </div>
        </div>
    @endif

    @if ($user->hasTwoFactorEnabled())
        <div class="flex flex-wrap items-center gap-3">
            <p class="text-sm font-medium text-emerald-800">{{ __('Two-factor authentication is enabled.') }}</p>
        </div>

        <div class="space-y-4 max-w-xl">
            @if ($user->password !== null)
                <div>
                    <x-input-label for="tf_disable_password" :value="__('Current password')" />
                    <x-text-input wire:model="current_password" id="tf_disable_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
                    <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
                </div>
            @else
                <div>
                    <x-input-label for="tf_disable_email" :value="__('Confirm your email')" />
                    <x-text-input wire:model="email_confirm" id="tf_disable_email" type="email" class="mt-1 block w-full" autocomplete="email" />
                    <x-input-error :messages="$errors->get('email_confirm')" class="mt-2" />
                </div>
            @endif

            <div class="flex flex-wrap gap-3">
                <x-danger-button type="button" wire:click="disable">
                    {{ __('Disable 2FA') }}
                </x-danger-button>

                <x-secondary-button type="button" wire:click="regenerateRecoveryCodes">
                    {{ __('Regenerate recovery codes') }}
                </x-secondary-button>
            </div>
        </div>
    @elseif ($user->hasPendingTwoFactorSetup())
        <div class="space-y-4 max-w-xl">
            <p class="text-sm text-ink/70">{{ __('Scan this QR code with your authenticator app, then enter the 6-digit code to confirm.') }}</p>

            <div class="rounded-xl border border-cream-300 bg-white p-4 inline-block ring-1 ring-ink/5 [&_svg]:max-w-[200px] [&_svg]:h-auto">
                {!! $qrSvg !!}
            </div>

            <div>
                <x-input-label for="tf_code" :value="__('Authentication code')" />
                <x-text-input wire:model="code" id="tf_code" type="text" class="mt-1 block w-full tracking-widest" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000" />
                <x-input-error :messages="$errors->get('code')" class="mt-2" />
            </div>

            <div class="flex flex-wrap gap-3">
                <x-primary-button type="button" wire:click="confirmSetup">
                    {{ __('Confirm and enable') }}
                </x-primary-button>
                <x-secondary-button type="button" wire:click="cancelSetup">
                    {{ __('Cancel') }}
                </x-secondary-button>
            </div>
        </div>
    @else
        <div class="space-y-4 max-w-xl">
            @if ($user->password !== null)
                <div>
                    <x-input-label for="tf_begin_password" :value="__('Current password')" />
                    <x-text-input wire:model="current_password" id="tf_begin_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
                    <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
                </div>
            @else
                <div>
                    <x-input-label for="tf_begin_email" :value="__('Confirm your email')" />
                    <x-text-input wire:model="email_confirm" id="tf_begin_email" type="email" class="mt-1 block w-full" autocomplete="email" />
                    <x-input-error :messages="$errors->get('email_confirm')" class="mt-2" />
                </div>
            @endif

            <div>
                <x-primary-button type="button" wire:click="beginSetup">
                    {{ __('Enable two-factor authentication') }}
                </x-primary-button>
            </div>
        </div>
    @endif
</section>
