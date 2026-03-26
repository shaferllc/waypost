<?php

use App\Models\User;
use Fleet\IdpClient\Models\LocalEmailLoginChallenge;
use Fleet\IdpClient\FleetEmailSignIn;
use Fleet\IdpClient\FleetIdp;
use Fleet\IdpClient\FleetIdpEmailLogin;
use Fleet\IdpClient\Support\EmailSignInUserOptions;
use Fleet\IdpClient\Support\ProfileEmailSignInConfirmation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    public string $current_password = '';

    public bool $confirmDisableCodeModal = false;

    public bool $confirmDisableMagicModal = false;

    private function validateCredentials(User $user): void
    {
        if ($user->password !== null) {
            $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
            ]);
        }
    }

    /**
     * @return array{allows_code: bool, allows_magic: bool}
     */
    /**
     * Caps mirror the guest email sign-in page: when Fleet password grant is configured,
     * {@see FleetSocialLoginPolicy} applies to all users on this app (not only Fleet-linked).
     */
    private function orgAllows(): array
    {
        return [
            'allows_code' => FleetEmailSignIn::loginPageOffersCode(),
            'allows_magic' => FleetEmailSignIn::loginPageOffersMagicLink(),
        ];
    }

    private function ensureFleetEmailLoginReady(User $user): void
    {
        if (FleetIdp::passwordManagedByIdp($user)) {
            if (! FleetIdpEmailLogin::isAvailable()) {
                throw ValidationException::withMessages([
                    'current_password' => __('This sign-in method is not set up on this app yet. Use your password or contact support.'),
                ]);
            }
        }
    }

    public function enableCode(): void
    {
        $user = Auth::user();
        assert($user instanceof User);
        $this->validateCredentials($user);

        $org = $this->orgAllows();
        if (! $org['allows_code']) {
            throw ValidationException::withMessages([
                'current_password' => __('Your organization has not enabled one-time email codes for this app.'),
            ]);
        }

        $this->ensureFleetEmailLoginReady($user);

        $this->sendEmailCodeConfirmationEmail($user);
        $this->reset('current_password');
    }

    public function resendEmailCodeConfirmation(): void
    {
        $user = Auth::user();
        assert($user instanceof User);

        if (! $this->emailCodeConfirmationPending($user)) {
            throw ValidationException::withMessages([
                'current_password' => __('There is no pending confirmation to resend.'),
            ]);
        }

        $this->validateCredentials($user);
        $this->ensureFleetEmailLoginReady($user);

        $org = $this->orgAllows();
        if (! $org['allows_code']) {
            throw ValidationException::withMessages([
                'current_password' => __('Your organization has not enabled one-time email codes for this app.'),
            ]);
        }

        $this->sendEmailCodeConfirmationEmail($user);
        $this->reset('current_password');
    }

    public function cancelEmailCodeConfirmation(): void
    {
        $user = Auth::user();
        assert($user instanceof User);

        if (! $this->emailCodeConfirmationPending($user)) {
            return;
        }

        $this->validateCredentials($user);

        ProfileEmailSignInConfirmation::clearEmailCodePending($user);

        $this->reset('current_password');
    }

    private function emailCodeConfirmationPending(User $user): bool
    {
        return ProfileEmailSignInConfirmation::emailCodeConfirmationPending($user);
    }

    private function sendEmailCodeConfirmationEmail(User $user): void
    {
        ProfileEmailSignInConfirmation::sendEmailCodeConfirmationMail($user);
    }

    public function enableMagic(): void
    {
        $user = Auth::user();
        assert($user instanceof User);
        $this->validateCredentials($user);

        $org = $this->orgAllows();
        if (! $org['allows_magic']) {
            throw ValidationException::withMessages([
                'current_password' => __('Your organization has not enabled magic sign-in links for this app.'),
            ]);
        }

        $this->ensureFleetEmailLoginReady($user);

        $this->sendMagicLinkConfirmationEmail($user);
        $this->reset('current_password');
    }

    public function resendMagicLinkConfirmation(): void
    {
        $user = Auth::user();
        assert($user instanceof User);

        if (! $this->magicLinkConfirmationPending($user)) {
            throw ValidationException::withMessages([
                'current_password' => __('There is no pending confirmation to resend.'),
            ]);
        }

        $this->validateCredentials($user);
        $this->ensureFleetEmailLoginReady($user);

        $org = $this->orgAllows();
        if (! $org['allows_magic']) {
            throw ValidationException::withMessages([
                'current_password' => __('Your organization has not enabled magic sign-in links for this app.'),
            ]);
        }

        $this->sendMagicLinkConfirmationEmail($user);
        $this->reset('current_password');
    }

    public function cancelMagicLinkConfirmation(): void
    {
        $user = Auth::user();
        assert($user instanceof User);

        if (! $this->magicLinkConfirmationPending($user)) {
            return;
        }

        $this->validateCredentials($user);

        ProfileEmailSignInConfirmation::clearMagicLinkPending($user);

        $this->reset('current_password');
    }

    private function magicLinkConfirmationPending(User $user): bool
    {
        return ProfileEmailSignInConfirmation::magicLinkConfirmationPending($user);
    }

    private function sendMagicLinkConfirmationEmail(User $user): void
    {
        ProfileEmailSignInConfirmation::sendMagicLinkConfirmationMail($user);
    }

    public function disableCode(): void
    {
        $user = Auth::user();
        assert($user instanceof User);
        $this->validateCredentials($user);

        ProfileEmailSignInConfirmation::setEmailCodeEnabledOnProfile($user, false);
        $this->clearChallengesIfFullyDisabled($user);
        $this->reset('current_password');
        $this->confirmDisableCodeModal = false;
    }

    public function cancelDisableCodeModal(): void
    {
        $this->confirmDisableCodeModal = false;
        $this->reset('current_password');
    }

    public function disableMagic(): void
    {
        $user = Auth::user();
        assert($user instanceof User);
        $this->validateCredentials($user);

        ProfileEmailSignInConfirmation::setMagicLinkEnabledOnProfile($user, false);
        $this->clearChallengesIfFullyDisabled($user);
        $this->reset('current_password');
        $this->confirmDisableMagicModal = false;
    }

    public function cancelDisableMagicModal(): void
    {
        $this->confirmDisableMagicModal = false;
        $this->reset('current_password');
    }

    private function clearChallengesIfFullyDisabled(User $user): void
    {
        if (! EmailSignInUserOptions::userAllowsCode($user) && ! EmailSignInUserOptions::userAllowsMagicLink($user)) {
            LocalEmailLoginChallenge::query()->where('email', strtolower((string) $user->email))->delete();
        }
    }
}; ?>

<div>
@php
    /** @var \App\Models\User|null $user */
    $user = auth()->user();
@endphp

@if ($user === null)
    <section class="space-y-6" aria-hidden="true"></section>
@else
@php
    $codeOn = EmailSignInUserOptions::userAllowsCode($user);
    $magicOn = EmailSignInUserOptions::userAllowsMagicLink($user);
    $anyOn = $codeOn || $magicOn;
    $magicPending = ProfileEmailSignInConfirmation::magicLinkConfirmationPending($user);
    $codePending = ProfileEmailSignInConfirmation::emailCodeConfirmationPending($user);
    $exclusive = EmailSignInUserOptions::mutuallyExclusiveCodeAndMagic();
    $codeBlockedByMagic = $exclusive && ($magicOn || $magicPending);
    $magicBlockedByCode = $exclusive && ($codeOn || $codePending);
    $orgAllowsCode = FleetEmailSignIn::loginPageOffersCode();
    $orgAllowsMagic = FleetEmailSignIn::loginPageOffersMagicLink();
@endphp
<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-ink">
            {{ __('Passwordless email sign-in') }}
        </h2>

        <p class="mt-1 text-sm text-ink/70">
            @if ($exclusive)
                {{ __('fleet-idp::email_sign_in.profile_exclusive_summary') }}
            @else
                {{ __('Turn on one or both options to sign in from the email sign-in page without your password. What you can enable depends on your organization’s settings.') }}
            @endif
        </p>
    </header>

    <div class="space-y-6 max-w-xl">
        @if ($anyOn)
            <p class="text-sm font-medium text-emerald-800">{{ __('Enabled for your account:') }}</p>
            <ul class="list-inside list-disc text-sm text-ink/65 space-y-1" role="list">
                @if ($codeOn)
                    <li>{{ __('One-time code to your email') }}</li>
                @endif
                @if ($magicOn)
                    <li>{{ __('Magic sign-in link to your email') }}</li>
                @endif
            </ul>
            <p class="text-sm text-ink/65">{{ __('Messages are sent by :app.', ['app' => config('app.name')]) }}</p>
        @elseif ($codePending && $magicPending)
            <p class="text-sm font-medium text-amber-900">{{ __('Confirm sign-in options from your email') }}</p>
            <p class="text-sm text-ink/70 mt-1">{{ __('We sent separate messages for one-time codes and magic links. Each stays off until you open its confirmation link. Links expire in 24 hours.') }}</p>
        @elseif ($codePending)
            <p class="text-sm font-medium text-amber-900">{{ __('One-time email code is not active yet') }}</p>
            <p class="text-sm text-ink/70 mt-1">{{ __('We sent a message to your email with a confirmation link. Code sign-in stays off until you open that link. The link expires in 24 hours.') }}</p>
        @elseif ($magicPending)
            <p class="text-sm font-medium text-amber-900">{{ __('Magic sign-in link is not active yet') }}</p>
            <p class="text-sm text-ink/70 mt-1">{{ __('We sent a message to your email with a confirmation link. Magic link sign-in stays off until you open that link. The link expires in 24 hours.') }}</p>
        @else
            <p class="text-sm text-ink/65">{{ __('Nothing enabled yet. Choose the options your organization allows below.') }}</p>
        @endif

        @if ($user->password !== null)
            <div>
                <x-input-label for="ec_password_gate" :value="__('Current password (required to change these settings)')" />
                <x-text-input wire:model="current_password" id="ec_password_gate" type="password" class="mt-1 block w-full" autocomplete="current-password" />
                <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
            </div>
        @endif

        @if ($orgAllowsCode || $codeOn || $orgAllowsMagic || $magicOn || $magicPending || $codePending)
            <div class="rounded-lg border border-cream-300/80 bg-white p-4 space-y-4">
                @if ($orgAllowsCode || $codeOn || $codePending)
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-ink">{{ __('One-time code') }}</p>
                            <p class="text-xs text-ink/60">{{ __('Receive a six-digit code to type on the sign-in page. You must confirm by email before it works.') }}</p>
                            @if ($codeOn && ! $orgAllowsCode)
                                <p class="text-xs text-amber-800 mt-1">{{ __('Your organization no longer allows codes. Turn this off or contact an admin.') }}</p>
                            @endif
                            @if ($codeBlockedByMagic && ! $codeOn && ! $codePending && $orgAllowsCode)
                                <p class="text-xs text-ink/55 mt-1">{{ __('fleet-idp::email_sign_in.profile_exclusive_code_blocked') }}</p>
                            @endif
                        </div>
                        <div class="flex shrink-0 flex-col items-stretch gap-2 sm:items-end">
                            @if ($codeOn)
                                <x-danger-button type="button" wire:click.prevent="$set('confirmDisableCodeModal', true)">
                                    {{ __('Turn off code') }}
                                </x-danger-button>
                            @elseif ($codePending)
                                <x-secondary-button type="button" wire:click.prevent="resendEmailCodeConfirmation">
                                    {{ __('Resend confirmation email') }}
                                </x-secondary-button>
                                <x-secondary-button type="button" wire:click.prevent="cancelEmailCodeConfirmation">
                                    {{ __('Cancel request') }}
                                </x-secondary-button>
                            @elseif ($orgAllowsCode && ! $codeBlockedByMagic)
                                <x-primary-button type="button" wire:click.prevent="enableCode">
                                    {{ __('Turn on code') }}
                                </x-primary-button>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($orgAllowsMagic || $magicOn || $magicPending)
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between @if($orgAllowsCode || $codeOn || $codePending) pt-2 border-t border-cream-200 @endif">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-ink">{{ __('Magic sign-in link') }}</p>
                            <p class="text-xs text-ink/60">{{ __('Receive a link that signs you in when you open it. You must confirm by email before it works.') }}</p>
                            @if ($magicOn && ! $orgAllowsMagic)
                                <p class="text-xs text-amber-800 mt-1">{{ __('Your organization no longer allows magic links. Turn this off or contact an admin.') }}</p>
                            @endif
                            @if ($magicBlockedByCode && ! $magicOn && ! $magicPending && $orgAllowsMagic)
                                <p class="text-xs text-ink/55 mt-1">{{ __('fleet-idp::email_sign_in.profile_exclusive_magic_blocked') }}</p>
                            @endif
                        </div>
                        <div class="flex shrink-0 flex-col items-stretch gap-2 sm:items-end">
                            @if ($magicOn)
                                <x-danger-button type="button" wire:click.prevent="$set('confirmDisableMagicModal', true)">
                                    {{ __('Turn off link') }}
                                </x-danger-button>
                            @elseif ($magicPending)
                                <x-secondary-button type="button" wire:click.prevent="resendMagicLinkConfirmation">
                                    {{ __('Resend confirmation email') }}
                                </x-secondary-button>
                                <x-secondary-button type="button" wire:click.prevent="cancelMagicLinkConfirmation">
                                    {{ __('Cancel request') }}
                                </x-secondary-button>
                            @elseif ($orgAllowsMagic && ! $magicBlockedByCode)
                                <x-primary-button type="button" wire:click.prevent="enableMagic">
                                    {{ __('Turn on link') }}
                                </x-primary-button>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>

    @if ($confirmDisableCodeModal)
        <x-fleet-idp::confirm-current-password-modal
            :title="__('Turn off one-time code')"
            :description="__('You will no longer receive sign-in codes by email until you turn this on again.')"
            confirm-wire-method="disableCode"
            cancel-wire-method="cancelDisableCodeModal"
            :require-password="$user->password !== null"
            password-input-id="ec_modal_disable_code_password"
            :confirm-button-label="__('Turn off code')"
        />
    @endif

    @if ($confirmDisableMagicModal)
        <x-fleet-idp::confirm-current-password-modal
            :title="__('Turn off magic link')"
            :description="__('You will no longer receive magic sign-in links by email until you turn this on again.')"
            confirm-wire-method="disableMagic"
            cancel-wire-method="cancelDisableMagicModal"
            :require-password="$user->password !== null"
            password-input-id="ec_modal_disable_magic_password"
            :confirm-button-label="__('Turn off link')"
        />
    @endif
</section>
@endif
</div>
