<nav class="-mx-3 flex flex-1 justify-end gap-1">
    @auth
        <a
            href="{{ url('/dashboard') }}"
            class="rounded-lg px-3 py-2 text-sm font-medium text-ink/80 ring-1 ring-transparent transition hover:text-ink hover:bg-cream-200/80 focus:outline-none focus-visible:ring-2 focus-visible:ring-sage focus-visible:ring-offset-2"
        >
            Dashboard
        </a>
    @else
        <a
            href="{{ route('login') }}"
            class="rounded-lg px-3 py-2 text-sm font-medium text-ink/80 ring-1 ring-transparent transition hover:text-ink hover:bg-cream-200/80 focus:outline-none focus-visible:ring-2 focus-visible:ring-sage focus-visible:ring-offset-2"
        >
            Log in
        </a>

        @if (Route::has('register'))
            <a
                href="{{ route('register') }}"
                class="rounded-lg px-3 py-2 text-sm font-medium text-ink/80 ring-1 ring-transparent transition hover:text-ink hover:bg-cream-200/80 focus:outline-none focus-visible:ring-2 focus-visible:ring-sage focus-visible:ring-offset-2"
            >
                Register
            </a>
        @endif
    @endauth
</nav>
