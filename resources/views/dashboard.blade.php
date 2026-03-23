<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Overview') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @php
                $counts = auth()->user()->projects()->withCount(['tasks', 'links'])->get();
                $projectCount = $counts->count();
                $taskCount = $counts->sum('tasks_count');
                $linkCount = $counts->sum('links_count');
            @endphp

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Projects</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $projectCount }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Tasks</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $taskCount }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Links</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $linkCount }}</p>
                </div>
            </div>

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-xl border border-slate-200/80">
                <div class="p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Open your roadmap</h3>
                        <p class="mt-1 text-slate-600">Create projects, track tasks, and keep important URLs together.</p>
                    </div>
                    <a
                        href="{{ route('projects.index') }}"
                        wire:navigate
                        class="inline-flex items-center justify-center rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2"
                    >
                        Go to projects
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
