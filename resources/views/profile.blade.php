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
            @if (session('error'))
                <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mb-4">
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-950" role="alert">
                        {{ session('error') }}
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

            @if (\Fleet\IdpClient\Support\ProfileTwoFactorSettings::showInProfile(auth()->user()))
                <div class="p-4 sm:p-8 bg-cream-50 border border-cream-300/80 shadow-sm sm:rounded-lg ring-1 ring-ink/5">
                    <div class="max-w-xl">
                        <livewire:profile.two-factor-authentication-form />
                    </div>
                </div>
            @endif

            @if (config('waypost.fleet_login_enabled') && \Fleet\IdpClient\Support\ProfileEmailSignInSettings::showInProfile(auth()->user()))
                <div class="p-4 sm:p-8 bg-cream-50 border border-cream-300/80 shadow-sm sm:rounded-lg ring-1 ring-ink/5">
                    <div class="max-w-xl">
                        <livewire:profile.email-code-sign-in-form />
                    </div>
                </div>
            @endif

            @if (config('waypost.fleet_login_enabled') && \Fleet\IdpClient\Support\ProfileFleetAccountSettings::showInProfile(auth()->user()))
                <div class="p-4 sm:p-8 bg-cream-50 border border-cream-300/80 shadow-sm sm:rounded-lg ring-1 ring-ink/5">
                    <div class="max-w-xl">
                        <livewire:profile.fleet-account-link-form />
                    </div>
                </div>
            @endif

            <div class="p-4 sm:p-8 bg-cream-50 border border-cream-300/80 shadow-sm sm:rounded-lg ring-1 ring-ink/5">
                <div class="max-w-xl">
                    <livewire:profile.delete-user-form />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
