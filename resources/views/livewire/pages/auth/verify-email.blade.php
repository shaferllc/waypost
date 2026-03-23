<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-ink">{{ __('Verify email') }}</h1>
        <p class="mt-2 text-sm text-ink/70 leading-relaxed">
            {{ __('Thanks for signing up! Before you continue, please verify your email using the link we sent. If you did not receive it, we can send another.') }}
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-primary-button wire:click="sendVerification" class="w-full justify-center sm:w-auto">
            {{ __('Resend verification email') }}
        </x-primary-button>

        <button wire:click="logout" type="button" class="text-sm font-medium text-ink/70 hover:text-ink">
            {{ __('Log out') }}
        </button>
    </div>
</div>
