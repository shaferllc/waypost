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
    <body class="font-sans text-slate-800 antialiased">
        <div class="min-h-screen flex flex-col bg-gradient-to-b from-slate-50 to-teal-50/40">
            <header class="border-b border-slate-200/80 bg-white/80 backdrop-blur-sm">
                <div class="max-w-lg mx-auto w-full px-4 sm:px-6 py-4 flex items-center justify-between">
                    <a href="/" wire:navigate class="flex items-center gap-2 min-w-0">
                        <x-application-logo class="h-9 w-auto shrink-0 fill-current text-teal-700" />
                        <span class="font-bold text-lg tracking-tight text-slate-900 truncate">{{ config('app.name') }}</span>
                    </a>
                </div>
            </header>

            <div class="flex-1 flex flex-col items-center justify-center px-4 py-10 sm:py-12">
                <div class="w-full max-w-md rounded-2xl border border-slate-200/80 bg-white p-6 sm:p-8 shadow-sm ring-1 ring-slate-900/5">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
