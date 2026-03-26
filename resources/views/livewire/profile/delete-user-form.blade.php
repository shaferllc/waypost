<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public string $password = '';

    public string $delete_confirmation = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $user = Auth::user();

        if ($user->password !== null) {
            $this->validate([
                'password' => ['required', 'string', 'current_password'],
            ]);
        } else {
            $this->validate([
                'delete_confirmation' => ['required', 'string', Rule::in(['DELETE'])],
            ]);
        }

        tap($user, $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-ink">
            {{ __('Delete Account') }}
        </h2>

        <p class="mt-1 text-sm text-ink/70">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Delete Account') }}</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" class="p-6">

            <h2 class="text-lg font-medium text-ink">
                {{ __('Are you sure you want to delete your account?') }}
            </h2>

            <p class="mt-1 text-sm text-ink/70">
                @if (auth()->user()->password !== null)
                    {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                @else
                    {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Type DELETE in capital letters to confirm.') }}
                @endif
            </p>

            <div class="mt-6">
                @if (auth()->user()->password !== null)
                    <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />

                    <x-text-input
                        wire:model="password"
                        id="password"
                        name="password"
                        type="password"
                        class="mt-1 block w-3/4"
                        placeholder="{{ __('Password') }}"
                    />

                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                @else
                    <x-input-label for="delete_confirmation" value="{{ __('Confirmation') }}" />

                    <x-text-input
                        wire:model="delete_confirmation"
                        id="delete_confirmation"
                        name="delete_confirmation"
                        type="text"
                        class="mt-1 block w-3/4"
                        placeholder="DELETE"
                        autocomplete="off"
                    />

                    <x-input-error :messages="$errors->get('delete_confirmation')" class="mt-2" />
                @endif
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ __('Delete Account') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
