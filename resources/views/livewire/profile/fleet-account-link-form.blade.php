<?php

use App\Models\User;
use Fleet\IdpClient\FleetIdp;
use Fleet\IdpClient\Support\ProfileFleetAccountSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public string $current_password = '';

    public function syncToFleet(): void
    {
        $user = Auth::user();
        assert($user instanceof User);

        $this->validate([
            'current_password' => ['required', 'string', 'current_password'],
        ]);

        $plain = $this->current_password;
        $this->reset('current_password');

        $result = FleetIdp::attemptProvisionUserToFleet($user, $plain);

        if ($result['ok']) {
            $message = $result['status'] === 'exists'
                ? __('fleet-idp::fleet_account.sync_success_exists')
                : __('fleet-idp::fleet_account.sync_success_created');
            session()->flash('status', $message);
            $this->redirect(route('profile'), navigate: true);

            return;
        }

        $key = match ($result['error']) {
            'missing_provisioning_token' => 'fleet-idp::fleet_account.sync_error_missing_provisioning_token',
            'missing_idp_url' => 'fleet-idp::fleet_account.sync_error_missing_idp_url',
            'missing_password' => 'fleet-idp::fleet_account.sync_error_missing_password',
            'unauthorized' => 'fleet-idp::fleet_account.sync_error_unauthorized',
            'bad_response' => 'fleet-idp::fleet_account.sync_error_bad_response',
            'exception' => 'fleet-idp::fleet_account.sync_error_exception',
            default => 'fleet-idp::fleet_account.sync_error_http',
        };

        $message = $key === 'fleet-idp::fleet_account.sync_error_http'
            ? __($key, ['status' => (string) ($result['http_status'] ?? '')])
            : __($key);

        session()->flash('error', $message);
        $this->redirect(route('profile'), navigate: true);
    }
}; ?>

<div>
@php
    $user = auth()->user();
    $oauthOn = ProfileFleetAccountSettings::oauthLinkAvailable();
    $syncOn = ProfileFleetAccountSettings::passwordSyncAvailable()
        && $user instanceof \App\Models\User
        && $user->password !== null;
@endphp

@if ($user === null)
    <section class="space-y-6" aria-hidden="true"></section>
@else
<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-ink">
            {{ __('fleet-idp::fleet_account.profile_title') }}
        </h2>

        <p class="mt-1 text-sm text-ink/70">
            {{ __('fleet-idp::fleet_account.profile_intro') }}
        </p>
    </header>

    <div class="space-y-8 max-w-xl">
        @if ($oauthOn)
            <div class="space-y-3">
                <h3 class="text-sm font-medium text-ink">
                    {{ __('fleet-idp::fleet_account.oauth_section_title') }}
                </h3>
                <p class="text-sm text-ink/65">
                    {{ __('fleet-idp::fleet_account.oauth_section_body') }}
                </p>
                <x-fleet-idp::oauth-button variant="waypost" class="max-w-md" />
            </div>
        @endif

        @if ($syncOn)
            <div class="space-y-4 @if($oauthOn) pt-6 border-t border-cream-200 @endif">
                <h3 class="text-sm font-medium text-ink">
                    {{ __('fleet-idp::fleet_account.sync_section_title') }}
                </h3>
                <p class="text-sm text-ink/65">
                    {{ __('fleet-idp::fleet_account.sync_section_body') }}
                </p>

                <div>
                    <x-input-label for="fleet_sync_password" :value="__('fleet-idp::fleet_account.current_password_label')" />
                    <x-text-input
                        wire:model="current_password"
                        id="fleet_sync_password"
                        type="password"
                        class="mt-1 block w-full max-w-md"
                        autocomplete="current-password"
                    />
                    <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
                </div>

                <div>
                    <x-secondary-button type="button" wire:click.prevent="syncToFleet" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="syncToFleet">{{ __('fleet-idp::fleet_account.sync_submit') }}</span>
                        <span wire:loading wire:target="syncToFleet">{{ __('Working…') }}</span>
                    </x-secondary-button>
                </div>
            </div>
        @endif
    </div>
</section>
@endif
</div>
