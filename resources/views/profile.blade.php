<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mb-4">
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950" role="status">
                        {{ session('status') }}
                    </div>
                </div>
            @endif
            <div class="p-4 sm:p-8 bg-cream-50 border border-cream-300/80 shadow-sm sm:rounded-lg ring-1 ring-ink/5">
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information-form />
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-cream-50 border border-cream-300/80 shadow-sm sm:rounded-lg ring-1 ring-ink/5">
                <div class="max-w-xl">
                    <livewire:profile.update-password-form />
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-cream-50 border border-cream-300/80 shadow-sm sm:rounded-lg ring-1 ring-ink/5">
                <div class="max-w-xl">
                    <livewire:profile.api-tokens-form />
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-cream-50 border border-cream-300/80 shadow-sm sm:rounded-lg ring-1 ring-ink/5">
                <div class="max-w-xl">
                    <livewire:profile.two-factor-authentication-form />
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-cream-50 border border-cream-300/80 shadow-sm sm:rounded-lg ring-1 ring-ink/5">
                <div class="max-w-xl">
                    <livewire:profile.delete-user-form />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
