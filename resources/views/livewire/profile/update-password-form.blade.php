<?php

use Fleet\IdpClient\FleetIdp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

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

        if (FleetIdp::passwordManagedByIdp($user)) {
            $this->updatePasswordForFleetLinkedUser($user);

            return;
        }

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

    private function updatePasswordForFleetLinkedUser(\Illuminate\Contracts\Auth\Authenticatable $user): void
    {
        $rules = [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ];

        try {
            $this->validate($rules);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        $result = FleetIdp::attemptFleetPasswordChange(
            (string) $user->email,
            $this->current_password,
            $this->password,
            $this->password_confirmation
        );

        if ($result['ok']) {
            $user->forceFill([
                'password' => Hash::make($this->password),
            ])->save();

            $this->reset('current_password', 'password', 'password_confirmation');
            $this->dispatch('password-updated');

            return;
        }

        if ($result['errors'] !== []) {
            $this->reset('current_password', 'password', 'password_confirmation');
            throw ValidationException::withMessages($result['errors']);
        }

        $this->reset('current_password', 'password', 'password_confirmation');

        $message = match ($result['error']) {
            'missing_provisioning_token', 'missing_idp_url' => __('Password change is not configured for this app. Contact support.'),
            'unauthorized' => __('Could not reach the sign-in service (unauthorized). Ask an admin to check provisioning credentials.'),
            'service_unavailable' => __('The sign-in service is unavailable. Try again later.'),
            default => __('Could not update your password. Try again.'),
        };

        throw ValidationException::withMessages([
            'password' => [$message],
        ]);
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-ink">
            {{ __('Update Password') }}
        </h2>

        @if (FleetIdp::passwordManagedByIdp(auth()->user()))
            <div
                class="mt-4 flex gap-3.5 rounded-xl border border-cream-300/90 bg-white/95 p-4 shadow-sm ring-1 ring-ink/5"
                role="note"
                aria-labelledby="fleet-password-callout-title"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sage/12 text-sage-dark"
                    aria-hidden="true"
                >
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                    </svg>
                </div>
                <div class="min-w-0 space-y-1.5 pt-0.5">
                    <p id="fleet-password-callout-title" class="text-sm font-semibold text-ink leading-snug">
                        {{ __('One password for your whole Fleet account') }}
                    </p>
                    <p class="text-sm leading-relaxed text-ink/70">
                        {{ __('Saving here updates the password on Fleet Auth. Every app you use with this email—including this one—will sign in with the new password.') }}
                    </p>
                </div>
            </div>
        @else
            <p class="mt-1 text-sm text-ink/70">
                @if (auth()->user()->password !== null)
                    {{ __('Ensure your account is using a long, random password to stay secure.') }}
                @else
                    {{ __('Add a password if you want to sign in with email as well as your social account.') }}
                @endif
            </p>
        @endif
    </header>

    <form wire:submit="updatePassword" class="mt-6 space-y-6">
        @if (FleetIdp::passwordManagedByIdp(auth()->user()) || auth()->user()->password !== null)
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
