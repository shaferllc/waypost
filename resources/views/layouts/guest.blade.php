<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name', 'Laravel'))</title>

        <x-favicons />

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-ink antialiased">
        <div class="flex min-h-screen flex-col bg-cream-100 lg:flex-row">
            {{-- Brand column (desktop) --}}
            <aside
                class="relative hidden overflow-hidden lg:flex lg:w-[min(44%,28rem)] lg:max-w-md lg:flex-col lg:justify-between lg:bg-gradient-to-br lg:from-sage-deeper lg:via-sage lg:to-[#3a5248] lg:px-10 lg:py-12 xl:px-12"
                aria-hidden="false"
            >
                <div
                    class="pointer-events-none absolute inset-0 opacity-[0.35]"
                    style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.14) 1px, transparent 0); background-size: 28px 28px;"
                ></div>
                <div class="absolute inset-0 bg-gradient-to-t from-black/25 to-transparent"></div>

                <div class="relative z-10 flex flex-1 flex-col">
                    <a href="{{ url('/') }}" wire:navigate class="inline-flex items-center gap-3 text-white">
                        <x-application-logo class="h-10 w-auto max-h-12 object-contain brightness-0 invert" />
                        <span class="text-lg font-bold tracking-tight">{{ config('app.name') }}</span>
                    </a>

                    <div class="mt-12">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-100/90">
                            {{ __('Your workspace') }}
                        </p>
                        <h2 class="mt-3 text-2xl font-semibold leading-snug tracking-tight text-white xl:text-3xl xl:leading-tight">
                            {{ __('Ship work you can see.') }}
                        </h2>
                        <p class="mt-4 max-w-sm text-sm leading-relaxed text-emerald-50/95 xl:text-base">
                            {{ __('Plan projects, move tasks, and share a roadmap—without teams or billing complexity.') }}
                        </p>

                        <ul class="mt-10 space-y-4 text-sm text-white/95">
                            <li class="flex items-start gap-3">
                                <x-waypost-icon name="check" class="mt-0.5 size-5 shrink-0 text-emerald-200" />
                                <span>{{ __('Multi-project workspaces with tasks and links') }}</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <x-waypost-icon name="check" class="mt-0.5 size-5 shrink-0 text-emerald-200" />
                                <span>{{ __('Board, roadmap, and OKRs in one lane') }}</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <x-waypost-icon name="check" class="mt-0.5 size-5 shrink-0 text-emerald-200" />
                                <span>{{ __('Share a read-only roadmap when you are ready') }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <p class="relative z-10 text-xs text-white/45">
                    © {{ date('Y') }} {{ config('app.name') }}
                </p>
            </aside>

            {{-- Form column --}}
            <div class="flex min-h-0 flex-1 flex-col bg-gradient-to-b from-cream-50 via-cream-50 to-cream-100">
                {{-- Desktop: account links --}}
                <div class="hidden border-b border-cream-200/70 bg-white/50 px-6 py-3 backdrop-blur-sm lg:flex lg:items-center lg:justify-end">
                    @if (Route::has('login'))
                        <livewire:welcome.navigation />
                    @endif
                </div>

                {{-- Mobile: logo + nav (plain links — avoid duplicating Livewire welcome nav) --}}
                <header
                    class="flex items-center justify-between gap-3 border-b border-cream-200/80 bg-white/85 px-4 py-3 backdrop-blur-sm lg:hidden"
                >
                    <a href="{{ url('/') }}" wire:navigate class="inline-flex min-w-0 items-center gap-2">
                        <x-application-logo class="h-8 w-auto shrink-0 object-contain" />
                        <span class="truncate font-semibold text-ink">{{ config('app.name') }}</span>
                    </a>
                    <nav class="flex shrink-0 flex-wrap items-center justify-end gap-1 sm:gap-2" aria-label="{{ __('Account') }}">
                        @auth
                            <a
                                href="{{ url('/dashboard') }}"
                                wire:navigate
                                class="rounded-lg px-2.5 py-2 text-sm font-medium text-ink/80 ring-1 ring-transparent transition hover:text-ink hover:bg-cream-200/80"
                            >
                                {{ __('Dashboard') }}
                            </a>
                        @else
                            @if (Route::has('login'))
                                <a
                                    href="{{ route('login') }}"
                                    wire:navigate
                                    class="rounded-lg px-2.5 py-2 text-sm font-medium text-ink/80 ring-1 ring-transparent transition hover:text-ink hover:bg-cream-200/80"
                                >
                                    {{ __('Log in') }}
                                </a>
                            @endif
                            @if (Route::has('register'))
                                <a
                                    href="{{ route('register') }}"
                                    wire:navigate
                                    class="rounded-lg px-2.5 py-2 text-sm font-medium text-sage-deeper ring-1 ring-transparent transition hover:bg-cream-200/80"
                                >
                                    {{ __('Register') }}
                                </a>
                            @endif
                        @endauth
                    </nav>
                </header>

                <div class="flex flex-1 flex-col items-center justify-center px-4 py-8 sm:px-6 sm:py-10">
                    <div class="w-full max-w-[26rem]">
                        <div
                            class="rounded-2xl border border-cream-200/90 bg-white p-6 shadow-[0_22px_60px_-28px_rgba(42,38,28,0.28)] ring-1 ring-ink/[0.04] sm:p-8"
                        >
                            <div class="auth-form-card">
                                @hasSection('content')
                                    @yield('content')
                                @else
                                    {{ $slot }}
                                @endif
                            </div>
                        </div>

                        <p class="mt-6 text-center text-xs text-ink/45">
                            {{ __('Protected by industry-standard encryption in transit.') }}
                        </p>
                    </div>
                </div>

                <x-site-footer variant="marketing" class="mt-auto border-t border-cream-200/60 bg-cream-50/50" />
            </div>
        </div>
    </body>
</html>
