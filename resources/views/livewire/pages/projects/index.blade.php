<?php

use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app')]
#[Title('Projects')]
class extends Component
{
    public string $name = '';

    public string $description = '';

    #[Computed]
    public function projects()
    {
        return Auth::user()
            ->projects()
            ->withCount(['tasks', 'links', 'versions', 'wishlistItems'])
            ->latest()
            ->get();
    }

    public function save(): void
    {
        $this->authorize('create', Project::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        Auth::user()->projects()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
        ]);

        $this->reset('name', 'description');
        unset($this->projects);
    }

    public function delete(Project $project): void
    {
        $this->authorize('delete', $project);
        $project->delete();
        unset($this->projects);
    }
}; ?>

<div class="py-10 px-4 sm:px-6 lg:px-8">
    <div class="max-w-5xl mx-auto space-y-10">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">Your projects</h1>
                <p class="mt-1 text-slate-600">Roadmaps, tasks, and bookmarks in one place.</p>
            </div>
            <a
                href="{{ route('dashboard') }}"
                wire:navigate
                class="text-sm font-medium text-teal-700 hover:text-teal-800"
            >
                ← Back to overview
            </a>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="text-lg font-semibold text-slate-900">New project</h2>
            <p class="mt-1 text-sm text-slate-500">Give it a name; you can add tasks and links inside.</p>
            <form wire:submit="save" class="mt-6 space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700">Name</label>
                    <input
                        wire:model="name"
                        id="name"
                        type="text"
                        class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"
                        placeholder="e.g. Launch landing page"
                        required
                    />
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700">Description <span class="text-slate-400 font-normal">(optional)</span></label>
                    <textarea
                        wire:model="description"
                        id="description"
                        rows="3"
                        class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"
                        placeholder="What is this project about?"
                    ></textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2"
                    >
                        Create project
                    </button>
                </div>
            </form>
        </div>

        <div>
            <h2 class="text-lg font-semibold text-slate-900 mb-4">All projects</h2>
            @if ($this->projects->isEmpty())
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/80 px-6 py-16 text-center">
                    <p class="text-slate-600">No projects yet. Create one above to get started.</p>
                </div>
            @else
                <ul class="space-y-3">
                    @foreach ($this->projects as $project)
                        <li
                            wire:key="project-{{ $project->id }}"
                            class="group flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-teal-200 hover:shadow-md"
                        >
                            <a href="{{ route('projects.show', $project) }}" wire:navigate class="min-w-0 flex-1">
                                <h3 class="font-semibold text-slate-900 group-hover:text-teal-700">{{ $project->name }}</h3>
                                @if ($project->description)
                                    <p class="mt-1 text-sm text-slate-600 line-clamp-2">{{ $project->description }}</p>
                                @endif
                                <p class="mt-2 text-xs text-slate-500">
                                    {{ $project->tasks_count }} {{ $project->tasks_count === 1 ? 'task' : 'tasks' }}
                                    ·
                                    {{ $project->versions_count }} {{ $project->versions_count === 1 ? 'version' : 'versions' }}
                                    ·
                                    {{ $project->wishlist_items_count }} ideas
                                    ·
                                    {{ $project->links_count }} {{ $project->links_count === 1 ? 'link' : 'links' }}
                                </p>
                            </a>
                            <div class="flex items-center gap-2 shrink-0">
                                <a
                                    href="{{ route('projects.show', $project) }}"
                                    wire:navigate
                                    class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-200"
                                >
                                    Open
                                </a>
                                <button
                                    type="button"
                                    wire:click="delete({{ $project->id }})"
                                    wire:confirm="Delete this project and all of its tasks and links?"
                                    class="rounded-lg px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
                                >
                                    Delete
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
