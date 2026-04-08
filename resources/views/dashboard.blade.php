<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            {{ __('Overview') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @php
                $accessibleProjectIds = \App\Models\Project::query()->accessible(auth()->user())->pluck('id');
                $counts = \App\Models\Project::query()
                    ->accessible(auth()->user())
                    ->notArchived()
                    ->withCount(['tasks', 'links'])
                    ->get();
                $projectCount = $counts->count();
                $taskCount = $counts->sum('tasks_count');
                $linkCount = $counts->sum('links_count');

                $activity = \App\Models\ChangelogEntry::query()
                    ->where(function ($q) use ($accessibleProjectIds): void {
                        $q->where('user_id', auth()->id());
                        if ($accessibleProjectIds->isNotEmpty()) {
                            $q->orWhereIn('project_id', $accessibleProjectIds);
                        }
                    })
                    ->latest()
                    ->limit(12)
                    ->with('project:id,name')
                    ->get();
            @endphp

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-cream-300 bg-cream-50 p-6 shadow-sm ring-1 ring-ink/5">
                    <p class="text-sm font-medium text-ink/55 inline-flex items-center gap-2">
                        <x-waypost-icon name="folder" class="h-5 w-5 text-sage-dark/80" />
                        Projects
                    </p>
                    <p class="mt-2 text-3xl font-bold text-ink">{{ $projectCount }}</p>
                </div>
                <div class="rounded-xl border border-cream-300 bg-cream-50 p-6 shadow-sm ring-1 ring-ink/5">
                    <p class="text-sm font-medium text-ink/55 inline-flex items-center gap-2">
                        <x-waypost-icon name="clipboard" class="h-5 w-5 text-sage-dark/80" />
                        Tasks
                    </p>
                    <p class="mt-2 text-3xl font-bold text-ink">{{ $taskCount }}</p>
                </div>
                <div class="rounded-xl border border-cream-300 bg-cream-50 p-6 shadow-sm ring-1 ring-ink/5">
                    <p class="text-sm font-medium text-ink/55 inline-flex items-center gap-2">
                        <x-waypost-icon name="link" class="h-5 w-5 text-sage-dark/80" />
                        Links
                    </p>
                    <p class="mt-2 text-3xl font-bold text-ink">{{ $linkCount }}</p>
                </div>
            </div>

            <div class="overflow-hidden bg-cream-50 shadow-sm sm:rounded-xl border border-cream-300/80 ring-1 ring-ink/5">
                <div class="p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-ink inline-flex items-center gap-2">
                            <x-waypost-icon name="roadmap" class="h-5 w-5 text-sage-dark/80" />
                            Open your roadmap
                        </h3>
                        <p class="mt-1 text-ink/70">Create projects, track tasks, and keep important URLs together.</p>
                    </div>
                    <a
                        href="{{ route('projects.index') }}"
                        wire:navigate
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-sage px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-sage-dark focus:outline-none focus:ring-2 focus:ring-sage focus:ring-offset-2"
                    >
                        <x-waypost-icon name="folder" class="h-4 w-4 text-white" />
                        Go to projects
                    </a>
                </div>
            </div>

            <div class="overflow-hidden bg-cream-50 shadow-sm sm:rounded-xl border border-cream-300/80 ring-1 ring-ink/5">
                <div class="border-b border-cream-200 px-6 py-4 sm:px-8">
                    <h3 class="text-lg font-semibold text-ink">Recent activity</h3>
                    <p class="mt-1 text-sm text-ink/60">Changelog from the web app and API across your projects.</p>
                </div>
                <ul class="divide-y divide-cream-200">
                    @forelse ($activity as $entry)
                        <li class="px-6 py-3 sm:px-8">
                            <p class="text-sm text-ink">{{ $entry->summary }}</p>
                            <p class="mt-1 text-xs text-ink/50">
                                {{ $entry->created_at->diffForHumans() }}
                                @if ($entry->project)
                                    · {{ $entry->project->name }}
                                @endif
                                · {{ $entry->source }}
                            </p>
                        </li>
                    @empty
                        <li class="px-6 py-8 sm:px-8 text-sm text-ink/55">No activity yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
