@props([
    'brandHref' => url('/'),
])

<header {{ $attributes->class(['border-b border-cream-300/80 bg-cream-50/95 backdrop-blur-sm']) }}>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center">
            <div class="shrink-0 flex items-center gap-2">
                <a href="{{ $brandHref }}" wire:navigate class="flex items-center gap-2 min-w-0">
                    <x-application-logo class="block h-9 w-auto shrink-0" />
                    <span class="hidden sm:inline font-bold text-lg tracking-tight text-ink truncate">{{ config('app.name') }}</span>
                </a>
            </div>
            @isset($actions)
                <div class="flex items-center shrink-0">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    </div>
</header>
