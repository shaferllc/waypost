<?php

use App\Models\Project;
use App\Models\ProjectLink;
use App\Models\Task;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')]
class extends Component
{
    public int $projectId;

    public string $taskTitle = '';

    public string $taskBody = '';

    public string $linkTitle = '';

    public string $linkUrl = '';

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->projectId = $project->id;
    }

    #[Computed]
    public function project(): Project
    {
        return Project::query()
            ->whereKey($this->projectId)
            ->where('user_id', auth()->id())
            ->with(['tasks', 'links'])
            ->firstOrFail();
    }

    public function addTask(): void
    {
        $this->authorize('update', $this->project);

        $validated = $this->validate([
            'taskTitle' => ['required', 'string', 'max:255'],
            'taskBody' => ['nullable', 'string', 'max:5000'],
        ]);

        $max = (int) $this->project->tasks()->max('position');

        $this->project->tasks()->create([
            'title' => $validated['taskTitle'],
            'body' => $validated['taskBody'] ?: null,
            'status' => 'todo',
            'position' => $max + 1,
        ]);

        $this->reset('taskTitle', 'taskBody');
        unset($this->project);
    }

    public function cycleTaskStatus(Task $task): void
    {
        $this->assertTaskOnProject($task);
        $this->authorize('update', $this->project);

        $order = ['todo', 'in_progress', 'done'];
        $i = array_search($task->status, $order, true);
        $next = $order[($i === false ? 0 : ($i + 1) % count($order))];
        $task->update(['status' => $next]);
        unset($this->project);
    }

    public function deleteTask(Task $task): void
    {
        $this->assertTaskOnProject($task);
        $this->authorize('update', $this->project);
        $task->delete();
        unset($this->project);
    }

    public function addLink(): void
    {
        $this->authorize('update', $this->project);

        $validated = $this->validate([
            'linkTitle' => ['required', 'string', 'max:120'],
            'linkUrl' => ['required', 'url', 'max:2048'],
        ]);

        $this->project->links()->create([
            'title' => $validated['linkTitle'],
            'url' => $validated['linkUrl'],
        ]);

        $this->reset('linkTitle', 'linkUrl');
        unset($this->project);
    }

    public function deleteLink(ProjectLink $link): void
    {
        $this->assertLinkOnProject($link);
        $this->authorize('update', $this->project);
        $link->delete();
        unset($this->project);
    }

    private function assertTaskOnProject(Task $task): void
    {
        if ($task->project_id !== $this->project->id) {
            abort(403);
        }
    }

    private function assertLinkOnProject(ProjectLink $link): void
    {
        if ($link->project_id !== $this->project->id) {
            abort(403);
        }
    }
}; ?>

@php
    $statusStyles = [
        'todo' => 'bg-slate-100 text-slate-800 ring-slate-200',
        'in_progress' => 'bg-amber-50 text-amber-900 ring-amber-200',
        'done' => 'bg-emerald-50 text-emerald-900 ring-emerald-200',
    ];
    $statusLabels = [
        'todo' => 'To do',
        'in_progress' => 'In progress',
        'done' => 'Done',
    ];
@endphp

<div class="py-10 px-4 sm:px-6 lg:px-8">
    <div class="max-w-5xl mx-auto space-y-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <a
                    href="{{ route('projects.index') }}"
                    wire:navigate
                    class="text-sm font-medium text-teal-700 hover:text-teal-800"
                >
                    ← All projects
                </a>
                <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-900">{{ $this->project->name }}</h1>
                @if ($this->project->description)
                    <p class="mt-2 text-slate-600 max-w-2xl">{{ $this->project->description }}</p>
                @endif
            </div>
        </div>

        <div class="grid gap-8 lg:grid-cols-5">
            <div class="lg:col-span-3 space-y-6">
                <section class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
                    <h2 class="text-lg font-semibold text-slate-900">Tasks</h2>
                    <p class="mt-1 text-sm text-slate-500">Click the status pill to cycle: to do → in progress → done.</p>

                    <form wire:submit="addTask" class="mt-6 space-y-3">
                        <div>
                            <label for="taskTitle" class="sr-only">Task title</label>
                            <input
                                wire:model="taskTitle"
                                id="taskTitle"
                                type="text"
                                class="block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"
                                placeholder="Task title"
                                required
                            />
                            @error('taskTitle')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="taskBody" class="sr-only">Notes</label>
                            <textarea
                                wire:model="taskBody"
                                id="taskBody"
                                rows="2"
                                class="block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"
                                placeholder="Notes (optional)"
                            ></textarea>
                            @error('taskBody')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex justify-end">
                            <button
                                type="submit"
                                class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-teal-500"
                            >
                                Add task
                            </button>
                        </div>
                    </form>

                    <ul class="mt-8 divide-y divide-slate-100">
                        @forelse ($this->project->tasks as $task)
                            <li wire:key="task-{{ $task->id }}" class="flex flex-col gap-3 py-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-slate-900">{{ $task->title }}</p>
                                    @if ($task->body)
                                        <p class="mt-1 text-sm text-slate-600 whitespace-pre-wrap">{{ $task->body }}</p>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-2 shrink-0">
                                    <button
                                        type="button"
                                        wire:click="cycleTaskStatus({{ $task->id }})"
                                        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$task->status] ?? $statusStyles['todo'] }}"
                                    >
                                        {{ $statusLabels[$task->status] ?? $task->status }}
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="deleteTask({{ $task->id }})"
                                        wire:confirm="Remove this task?"
                                        class="text-sm text-red-700 hover:text-red-800"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </li>
                        @empty
                            <li class="py-8 text-center text-sm text-slate-500">No tasks yet. Add one above.</li>
                        @endforelse
                    </ul>
                </section>
            </div>

            <div class="lg:col-span-2 space-y-6">
                <section class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
                    <h2 class="text-lg font-semibold text-slate-900">Links</h2>
                    <p class="mt-1 text-sm text-slate-500">Specs, repos, designs, docs—anything for this project.</p>

                    <form wire:submit="addLink" class="mt-6 space-y-3">
                        <div>
                            <label for="linkTitle" class="block text-xs font-medium text-slate-600">Label</label>
                            <input
                                wire:model="linkTitle"
                                id="linkTitle"
                                type="text"
                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500"
                                placeholder="GitHub, Figma, Notion…"
                                required
                            />
                            @error('linkTitle')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="linkUrl" class="block text-xs font-medium text-slate-600">URL</label>
                            <input
                                wire:model="linkUrl"
                                id="linkUrl"
                                type="url"
                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500"
                                placeholder="https://"
                                required
                            />
                            @error('linkUrl')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex justify-end">
                            <button
                                type="submit"
                                class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-slate-700"
                            >
                                Add link
                            </button>
                        </div>
                    </form>

                    <ul class="mt-6 space-y-2">
                        @forelse ($this->project->links as $link)
                            <li
                                wire:key="link-{{ $link->id }}"
                                class="flex items-start justify-between gap-2 rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-2"
                            >
                                <a
                                    href="{{ $link->url }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="min-w-0 text-sm font-medium text-teal-700 hover:text-teal-800 underline-offset-2 hover:underline"
                                >
                                    {{ $link->title }}
                                </a>
                                <button
                                    type="button"
                                    wire:click="deleteLink({{ $link->id }})"
                                    wire:confirm="Remove this link?"
                                    class="shrink-0 text-xs text-slate-500 hover:text-red-600"
                                >
                                    ×
                                </button>
                            </li>
                        @empty
                            <li class="py-6 text-center text-sm text-slate-500">No links yet.</li>
                        @endforelse
                    </ul>
                </section>
            </div>
        </div>
    </div>
</div>
