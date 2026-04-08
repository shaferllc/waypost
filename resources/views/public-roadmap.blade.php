<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $project->name }} — Roadmap — {{ config('app.name') }}</title>
        <x-favicons />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-cream-100 text-ink">
        @php
            $planningLabels = [
                \App\Models\Task::PLANNING_ON_TIME => 'On time',
                \App\Models\Task::PLANNING_IN_PROGRESS => 'In progress',
                \App\Models\Task::PLANNING_NOT_STARTED => 'Not started',
                \App\Models\Task::PLANNING_BEHIND => 'Behind',
                \App\Models\Task::PLANNING_BLOCKED => 'Blocked',
            ];
            $pubTimelineTasks = $project->tasks->filter(fn ($t) => $t->initiativeStart() && $t->initiativeEnd())->values();
        @endphp
        <div class="min-h-screen py-10 px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto space-y-10">
                <header>
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink/50 inline-flex items-center gap-1.5">
                        <x-waypost-icon name="roadmap" class="h-3.5 w-3.5" />
                        Shared roadmap
                    </p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight">{{ $project->name }}</h1>
                    @if ($shareName)
                        <p class="mt-1 text-sm text-ink/60">{{ $shareName }}</p>
                    @endif
                    @if ($project->description)
                        <p class="mt-4 text-ink/75">{{ $project->description }}</p>
                    @endif
                </header>

                @if ($project->okrGoals->isNotEmpty())
                    <section class="rounded-2xl border border-cream-300 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-ink inline-flex items-center gap-2">
                            <x-waypost-icon name="okrs" class="h-5 w-5 text-sage-dark/80" />
                            Goals &amp; OKRs
                        </h2>
                        <p class="mt-1 text-sm text-ink/55">High-level objectives and measurable key results (read-only).</p>
                        <div class="mt-6 space-y-6">
                            @foreach ($project->okrGoals as $goal)
                                <div class="rounded-xl border border-cream-200 bg-cream-50/50 p-4">
                                    <h3 class="font-semibold text-ink">{{ $goal->title }}</h3>
                                    <p class="mt-1 text-xs text-ink/55">Overall: <span class="font-semibold text-sage-dark">{{ $goal->averageProgress() }}%</span></p>
                                    <ul class="mt-4 space-y-4">
                                        @foreach ($goal->objectives as $objective)
                                            <li>
                                                <p class="text-sm font-medium text-ink">{{ $objective->title }}</p>
                                                <p class="text-xs text-ink/55">Objective: <span class="font-semibold text-sage-dark">{{ $objective->averageProgress() }}%</span></p>
                                                <ul class="mt-2 space-y-1 text-sm text-ink/80">
                                                    @foreach ($objective->keyResults as $kr)
                                                        <li class="flex justify-between gap-2">
                                                            <span>{{ $kr->title }}</span>
                                                            <span class="shrink-0 font-semibold text-sage-dark">{{ $kr->progress }}%</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($pubTimelineTasks->isNotEmpty())
                    <section class="rounded-2xl border border-cream-300 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-ink inline-flex items-center gap-2">
                            <x-waypost-icon name="timeline" class="h-5 w-5 text-sage-dark/80" />
                            Initiative timeline
                        </h2>
                        <p class="mt-1 text-sm text-ink/55">Scheduled work with start/end dates (or inferred from due dates).</p>
                        <div class="mt-6">
                            @include('partials.task-initiative-timeline', [
                                'tasks' => $project->tasks,
                                'interactive' => false,
                            ])
                        </div>
                    </section>
                @elseif ($project->tasks->isNotEmpty())
                    <section class="rounded-xl border border-dashed border-cream-300 bg-cream-50/80 p-5 text-sm text-ink/60">
                        <p class="font-medium text-ink">No initiative dates yet</p>
                        <p class="mt-1">When tasks get start/end dates (or due dates), a timeline will appear here for stakeholders.</p>
                    </section>
                @endif

                <section>
                    <h2 class="text-lg font-semibold text-ink inline-flex items-center gap-2">
                        <x-waypost-icon name="flag" class="h-5 w-5 text-sage-dark/80" />
                        Versions &amp; milestones
                    </h2>
                    <p class="mt-1 text-sm text-ink/55">Planned releases and the work tied to each.</p>
                    <div class="relative mt-6 space-y-6 pl-4 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-px before:bg-cream-300">
                        @foreach ($project->versions as $version)
                            <article class="relative rounded-2xl border border-cream-300 bg-white p-5 shadow-sm pl-6">
                                <span class="absolute -left-[1.15rem] top-6 flex h-3 w-3 rounded-full border-2 border-white bg-sage-light ring-2 ring-cream-300"></span>
                                <h3 class="text-lg font-semibold text-ink">{{ $version->name }}</h3>
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
                                    <ul class="mt-4 space-y-2 border-t border-cream-200 pt-4 text-sm">
                                        @foreach ($vTasks as $vt)
                                            <li class="text-ink">
                                                <span class="font-medium">• {{ $vt->title }}</span>
                                                @if ($vt->okrObjective)
                                                    <span class="mt-0.5 block text-xs text-ink/55">
                                                        OKR: {{ $vt->okrObjective->goal->title }} › {{ $vt->okrObjective->title }}
                                                    </span>
                                                @endif
                                                @if ($vt->planning_status)
                                                    <span class="ms-2 inline-block rounded-full bg-cream-100 px-2 py-0.5 text-[10px] font-semibold text-ink/80">
                                                        {{ $planningLabels[$vt->planning_status] ?? $vt->planning_status }}
                                                    </span>
                                                @endif
                                                @if ($vt->linksAsTarget->isNotEmpty())
                                                    <span class="ms-2 text-xs font-semibold text-amber-800">Blocked by {{ $vt->linksAsTarget->first()->source->title }}</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </article>
                        @endforeach
                    </div>

                    @if ($project->versions->isEmpty())
                        <p class="mt-4 text-sm text-ink/55">No roadmap versions yet.</p>
                    @endif
                </section>
            </div>
        </div>
    </body>
</html>
