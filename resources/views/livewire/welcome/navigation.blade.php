<?php

use Livewire\Component;

new class extends Component {};
?>

<nav class="flex items-center justify-end gap-1 sm:gap-2">
    @auth
        <a
            href="{{ url('/dashboard') }}"
            class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-ink/80 ring-1 ring-transparent transition hover:text-ink hover:bg-cream-200/80 focus:outline-none focus-visible:ring-2 focus-visible:ring-sage focus-visible:ring-offset-2"
        >
            <x-waypost-icon name="home" class="h-4 w-4 text-sage-dark/90" />
            Dashboard
        </a>
    @else
        <a
            href="{{ route('login') }}"
            class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-ink/80 ring-1 ring-transparent transition hover:text-ink hover:bg-cream-200/80 focus:outline-none focus-visible:ring-2 focus-visible:ring-sage focus-visible:ring-offset-2"
        >
            <x-waypost-icon name="login" class="h-4 w-4 text-sage-dark/90" />
            Log in
        </a>

        @if (Route::has('register'))
            <a
                href="{{ route('register') }}"
                class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-ink/80 ring-1 ring-transparent transition hover:text-ink hover:bg-cream-200/80 focus:outline-none focus-visible:ring-2 focus-visible:ring-sage focus-visible:ring-offset-2"
            >
                <x-waypost-icon name="register" class="h-4 w-4 text-sage-dark/90" />
                Register
            </a>
        @endif
    @endauth
</nav>
