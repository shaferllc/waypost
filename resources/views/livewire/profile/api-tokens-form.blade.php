<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $token_name = '';

    public ?string $plain_text_token = null;

    public function createToken(): void
    {
        $validated = $this->validate([
            'token_name' => ['required', 'string', 'max:255'],
        ]);

        $token = Auth::user()->createToken($validated['token_name']);

        $this->plain_text_token = $token->plainTextToken;
        $this->reset('token_name');
        $this->dispatch('api-token-created');
    }

    public function revokeToken(int $tokenId): void
    {
        Auth::user()->tokens()->whereKey($tokenId)->delete();
        $this->dispatch('api-token-revoked');
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-ink">
            {{ __('API tokens') }}
        </h2>

        <p class="mt-1 text-sm text-ink/70">
            {{ __('Create a token to add wishlist ideas from other apps or a browser extension. Use an Authorization header: Bearer plus your token.') }}
        </p>
        <p class="mt-2 text-sm">
            <a href="{{ route('docs.api') }}" wire:navigate class="font-medium text-sage-dark hover:text-sage-deeper underline">
                {{ __('View full API documentation') }}
            </a>
        </p>
    </header>

    @if ($plain_text_token)
        <div class="mt-6 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-medium">{{ __('Copy this token now. You will not see it again.') }}</p>
            <code class="mt-2 block break-all rounded bg-white p-2 text-xs text-ink">{{ $plain_text_token }}</code>
        </div>
    @endif

    <form wire:submit="createToken" class="mt-6 space-y-6">
        <div>
            <x-input-label for="api_token_name" :value="__('Token name')" />
            <x-text-input wire:model="token_name" id="api_token_name" name="token_name" type="text" class="mt-1 block w-full" placeholder="{{ __('e.g. Browser bookmarklet') }}" autocomplete="off" />
            <x-input-error :messages="$errors->get('token_name')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Create token') }}</x-primary-button>

            <x-action-message class="me-3" on="api-token-created">
                {{ __('Token created.') }}
            </x-action-message>
        </div>
    </form>

    <ul class="mt-8 divide-y divide-cream-200 border-t border-cream-200 pt-6 text-sm" wire:key="token-list">
        @forelse (Auth::user()->tokens()->orderBy('name')->get() as $token)
            <li class="flex items-center justify-between gap-4 py-3" wire:key="token-{{ $token->id }}">
                <div>
                    <span class="font-medium text-ink">{{ $token->name }}</span>
                    @if ($token->last_used_at)
                        <p class="text-xs text-ink/55">{{ __('Last used') }} {{ $token->last_used_at->diffForHumans() }}</p>
                    @endif
                </div>
                <button
                    type="button"
                    wire:click="revokeToken({{ $token->id }})"
                    class="text-sm text-red-600 hover:text-red-800"
                >
                    {{ __('Revoke') }}
                </button>
            </li>
        @empty
            <li class="py-3 text-ink/55">{{ __('No tokens yet.') }}</li>
        @endforelse
    </ul>
</section>
