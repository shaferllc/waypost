<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-ink antialiased">
        <div class="min-h-screen flex flex-col bg-gradient-to-b from-cream-100 via-cream-50 to-gold-pale/25">
            <x-site-header>
                <x-slot name="actions">
                    @if (Route::has('login'))
                        <livewire:welcome.navigation />
                    @endif
                </x-slot>
            </x-site-header>

            <div class="flex-1 flex flex-col items-center justify-center px-4 py-10 sm:py-12">
                <div class="w-full max-w-md rounded-2xl border border-cream-300/80 bg-cream-50 p-6 sm:p-8 shadow-sage ring-1 ring-ink/5">
                    {{ $slot }}
                </div>
            </div>

            <x-site-footer variant="marketing" class="mt-auto" />
        </div>
    </body>
</html>
