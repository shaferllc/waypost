@props([
    'variant' => 'marketing',
])

<footer {{ $attributes->class(['border-t border-cream-300/80 bg-cream-50/90']) }}>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
            <div class="max-w-md">
                <p class="text-sm font-semibold text-ink">{{ config('app.name') }}</p>
                <p class="mt-1 text-sm text-ink/60 leading-relaxed">
                    {{ __('Roadmaps, tasks, and links for makers and small teams.') }}
                </p>
                <p class="mt-3 text-xs text-ink/50">
                    © {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
                </p>
            </div>
            <nav class="flex flex-wrap gap-x-6 gap-y-2 text-sm shrink-0" aria-label="{{ __('Footer') }}">
                <a href="{{ url('/') }}" wire:navigate class="text-ink/70 hover:text-ink">{{ __('Home') }}</a>
                @if ($variant === 'app')
                    <a href="{{ route('dashboard') }}" wire:navigate class="text-ink/70 hover:text-ink">{{ __('Dashboard') }}</a>
                    <a href="{{ route('projects.index') }}" wire:navigate class="text-ink/70 hover:text-ink">{{ __('Projects') }}</a>
                    <a href="{{ route('docs.api') }}" wire:navigate class="text-ink/70 hover:text-ink">{{ __('API docs') }}</a>
                    <a href="{{ route('profile') }}" wire:navigate class="text-ink/70 hover:text-ink">{{ __('Profile') }}</a>
                @else
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" wire:navigate class="text-ink/70 hover:text-ink">{{ __('Log in') }}</a>
                    @endif
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" wire:navigate class="text-ink/70 hover:text-ink">{{ __('Register') }}</a>
                    @endif
                @endif
            </nav>
        </div>
    </div>
</footer>
