@props([
    'name',
])

{{-- Outer span holds size + text color (stroke uses currentColor). Inner SVG fills the box. --}}
<span {{ $attributes->merge(['class' => 'waypost-icon inline-flex items-center justify-center shrink-0 text-ink size-5']) }}>
    @switch($name)
        @case('board')
            <x-heroicon-o-view-columns class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('roadmap')
            <x-heroicon-o-map class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('okrs')
            <x-heroicon-o-chart-bar class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('wishlist')
            <x-heroicon-o-sparkles class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('links')
            <x-heroicon-o-link class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('sync')
            <x-heroicon-o-arrows-right-left class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('settings')
            <x-heroicon-o-cog-6-tooth class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('back')
            <x-heroicon-o-arrow-left class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('grip')
            <x-heroicon-o-bars-3 class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('columns')
            <x-heroicon-o-view-columns class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('rows')
            <x-heroicon-o-list-bullet class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('list')
            <x-heroicon-o-list-bullet class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('timeline')
            <x-heroicon-o-calendar-days class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('export')
            <x-heroicon-o-arrow-down-tray class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('funnel')
            <x-heroicon-o-funnel class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('chevron-left')
            <x-heroicon-o-chevron-left class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('chevron-right')
            <x-heroicon-o-chevron-right class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('folder')
            <x-heroicon-o-folder class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('clipboard')
            <x-heroicon-o-clipboard-document-list class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('link')
            <x-heroicon-o-link class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('open-external')
            <x-heroicon-o-arrow-top-right-on-square class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('trash')
            <x-heroicon-o-trash class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('flag')
            <x-heroicon-o-flag class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('clock')
            <x-heroicon-o-clock class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('exclamation')
            <x-heroicon-o-exclamation-triangle class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('search')
            <x-heroicon-o-magnifying-glass class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('home')
            <x-heroicon-o-home class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('login')
            <x-heroicon-o-arrow-right-on-rectangle class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('register')
            <x-heroicon-o-user-plus class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('api')
            <x-heroicon-o-document-text class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('profile')
            <x-heroicon-o-user-circle class="size-full text-inherit" aria-hidden="true" />
            @break
    @case('logout')
        <x-heroicon-o-arrow-left-on-rectangle class="size-full text-inherit" aria-hidden="true" />
        @break
        @case('check')
            <x-heroicon-o-check-circle class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('matrix')
            <x-heroicon-o-squares-2x2 class="size-full text-inherit" aria-hidden="true" />
            @break
        @case('eisenhower')
            <x-heroicon-o-squares-plus class="size-full text-inherit" aria-hidden="true" />
            @break
    @default
            <x-heroicon-o-question-mark-circle class="size-full text-inherit" aria-hidden="true" />
    @endswitch
</span>
