<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased font-sans text-ink bg-gradient-to-b from-cream-100 via-cream-50 to-gold-pale/25 min-h-screen">
        <div class="relative min-h-screen flex flex-col">
            <header class="border-b border-cream-300/80 bg-cream-50/90 backdrop-blur-sm">
                <div class="max-w-5xl mx-auto px-6 py-6 flex items-center justify-between gap-4">
                    <a href="/" wire:navigate class="flex items-center gap-3 min-w-0">
                        <x-application-logo class="h-10 w-auto sm:h-11 shrink-0" />
                        <span class="text-xl font-bold tracking-tight text-ink truncate">{{ config('app.name') }}</span>
                    </a>
                    @if (Route::has('login'))
                        <livewire:welcome.navigation />
                    @endif
                </div>
            </header>

            <main class="flex-1 flex items-center px-6 py-16">
                <div class="max-w-2xl mx-auto text-center">
                    <p class="text-sm font-semibold uppercase tracking-widest text-sage-dark">Free & simple</p>
                    <h1 class="mt-4 text-4xl sm:text-5xl font-bold tracking-tight text-ink">
                        Your roadmap, tasks, and links in one lane
                    </h1>
                    <p class="mt-6 text-lg text-ink/70 leading-relaxed">
                        {{ config('app.name') }} helps solo makers and small teams ship: multiple projects, a clear task list, and a pin board of URLs for each effort—without teams or billing complexity.
                    </p>
                    <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                        @auth
                            <a
                                href="{{ route('dashboard') }}"
                                wire:navigate
                                class="inline-flex rounded-xl bg-sage px-6 py-3 text-sm font-semibold text-cream-50 shadow-sage hover:bg-sage-dark"
                            >
                                Open dashboard
                            </a>
                        @else
                            @if (Route::has('register'))
                                <a
                                    href="{{ route('register') }}"
                                    wire:navigate
                                    class="inline-flex rounded-xl bg-sage px-6 py-3 text-sm font-semibold text-cream-50 shadow-sage hover:bg-sage-dark"
                                >
                                    Create account
                                </a>
                            @endif
                            <a
                                href="{{ route('login') }}"
                                wire:navigate
                                class="inline-flex rounded-xl border border-cream-300 bg-white px-6 py-3 text-sm font-semibold text-ink hover:bg-cream-100"
                            >
                                Log in
                            </a>
                        @endauth
                    </div>
                    <ul class="mt-16 grid gap-4 text-left sm:grid-cols-3 text-sm text-ink/70">
                        <li class="rounded-xl border border-cream-300 bg-cream-50/90 p-4 shadow-sm ring-1 ring-ink/5">
                            <span class="font-semibold text-ink">Projects</span>
                            <p class="mt-1">Separate spaces for every idea or client.</p>
                        </li>
                        <li class="rounded-xl border border-cream-300 bg-cream-50/90 p-4 shadow-sm ring-1 ring-ink/5">
                            <span class="font-semibold text-ink">Tasks</span>
                            <p class="mt-1">To do, in progress, and done—fast to update.</p>
                        </li>
                        <li class="rounded-xl border border-cream-300 bg-cream-50/90 p-4 shadow-sm ring-1 ring-ink/5">
                            <span class="font-semibold text-ink">Links</span>
                            <p class="mt-1">Repos, docs, and references beside the work.</p>
                        </li>
                    </ul>
                </div>
            </main>

            <footer class="py-8 text-center text-xs text-ink/55">
                Built with Laravel {{ Illuminate\Foundation\Application::VERSION }}
            </footer>
        </div>
    </body>
</html>
