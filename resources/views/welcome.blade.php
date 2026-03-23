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
    <body class="antialiased font-sans text-slate-800 bg-gradient-to-b from-slate-50 to-teal-50/40 min-h-screen">
        <div class="relative min-h-screen flex flex-col">
            <header class="border-b border-slate-200/80 bg-white/70 backdrop-blur-sm">
                <div class="max-w-5xl mx-auto px-6 py-6 flex items-center justify-between">
                    <span class="text-xl font-bold tracking-tight text-slate-900">{{ config('app.name') }}</span>
                    @if (Route::has('login'))
                        <livewire:welcome.navigation />
                    @endif
                </div>
            </header>

            <main class="flex-1 flex items-center px-6 py-16">
                <div class="max-w-2xl mx-auto text-center">
                    <p class="text-sm font-semibold uppercase tracking-widest text-teal-700">Free & simple</p>
                    <h1 class="mt-4 text-4xl sm:text-5xl font-bold tracking-tight text-slate-900">
                        Your roadmap, tasks, and links in one lane
                    </h1>
                    <p class="mt-6 text-lg text-slate-600 leading-relaxed">
                        {{ config('app.name') }} helps solo makers and small teams ship: multiple projects, a clear task list, and a pin board of URLs for each effort—without teams or billing complexity.
                    </p>
                    <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                        @auth
                            <a
                                href="{{ route('dashboard') }}"
                                wire:navigate
                                class="inline-flex rounded-xl bg-teal-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-teal-600/25 hover:bg-teal-500"
                            >
                                Open dashboard
                            </a>
                        @else
                            @if (Route::has('register'))
                                <a
                                    href="{{ route('register') }}"
                                    wire:navigate
                                    class="inline-flex rounded-xl bg-teal-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-teal-600/25 hover:bg-teal-500"
                                >
                                    Create account
                                </a>
                            @endif
                            <a
                                href="{{ route('login') }}"
                                wire:navigate
                                class="inline-flex rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-800 hover:bg-slate-50"
                            >
                                Log in
                            </a>
                        @endauth
                    </div>
                    <ul class="mt-16 grid gap-4 text-left sm:grid-cols-3 text-sm text-slate-600">
                        <li class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                            <span class="font-semibold text-slate-900">Projects</span>
                            <p class="mt-1">Separate spaces for every idea or client.</p>
                        </li>
                        <li class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                            <span class="font-semibold text-slate-900">Tasks</span>
                            <p class="mt-1">To do, in progress, and done—fast to update.</p>
                        </li>
                        <li class="rounded-xl border border-slate-200 bg-white/80 p-4 shadow-sm">
                            <span class="font-semibold text-slate-900">Links</span>
                            <p class="mt-1">Repos, docs, and references beside the work.</p>
                        </li>
                    </ul>
                </div>
            </main>

            <footer class="py-8 text-center text-xs text-slate-500">
                Built with Laravel {{ Illuminate\Foundation\Application::VERSION }}
            </footer>
        </div>
    </body>
</html>
