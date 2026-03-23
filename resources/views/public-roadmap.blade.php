<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $project->name }} — Roadmap — {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-cream-100 text-ink">
        <div class="min-h-screen py-10 px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto space-y-8">
                <header>
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink/50">Shared roadmap</p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight">{{ $project->name }}</h1>
                    @if ($shareName)
                        <p class="mt-1 text-sm text-ink/60">{{ $shareName }}</p>
                    @endif
                    @if ($project->description)
                        <p class="mt-4 text-ink/75">{{ $project->description }}</p>
                    @endif
                </header>

                <div class="relative space-y-6 pl-4 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-px before:bg-cream-300">
                    @foreach ($project->versions as $version)
                        <article class="relative rounded-2xl border border-cream-300 bg-white p-5 shadow-sm pl-6">
                            <span class="absolute -left-[1.15rem] top-6 flex h-3 w-3 rounded-full border-2 border-white bg-sage-light ring-2 ring-cream-300"></span>
                            <h2 class="text-lg font-semibold text-ink">{{ $version->name }}</h2>
                            <div class="mt-1 flex flex-wrap gap-2 text-xs text-ink/70">
                                @if ($version->target_date)
                                    <span>Target {{ $version->target_date->format('M j, Y') }}</span>
                                @endif
                                @if ($version->released_at)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-900">Released {{ $version->released_at->format('M j, Y') }}</span>
                                @endif
                            </div>
                            @if ($version->description)
                                <p class="mt-3 text-sm text-ink/70 whitespace-pre-wrap">{{ $version->description }}</p>
                            @endif
                            @php
                                $vTasks = $project->tasks->where('version_id', $version->id);
                            @endphp
                            @if ($vTasks->isNotEmpty())
                                <ul class="mt-4 space-y-1 border-t border-cream-200 pt-4 text-sm">
                                    @foreach ($vTasks as $vt)
                                        <li class="text-ink">• {{ $vt->title }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </article>
                    @endforeach
                </div>

                @if ($project->versions->isEmpty())
                    <p class="text-sm text-ink/55">No roadmap versions yet.</p>
                @endif
            </div>
        </div>
    </body>
</html>
