<?php

use App\Models\Project;
use App\Services\ProjectCursorTokenIssuer;
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

    public string $url = '';

    public ?int $lastCreatedProjectId = null;

    public ?string $lastCreatedCursorToken = null;

    public bool $showArchived = false;

    #[Computed]
    public function projects()
    {
        $query = Project::query()
            ->accessible(Auth::user())
            ->withCount(['tasks', 'links', 'versions', 'wishlistItems'])
            ->latest('updated_at');

        if (! $this->showArchived) {
            $query->notArchived();
        }

        return $query->get();
    }

    public function save(): void
    {
        $this->authorize('create', Project::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'url' => ['nullable', 'url', 'max:2048'],
        ]);

        $project = Auth::user()->projects()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'url' => $validated['url'] ?: null,
        ]);

        $this->lastCreatedProjectId = $project->id;
        $this->lastCreatedCursorToken = app(ProjectCursorTokenIssuer::class)->issue($project, Auth::user());

        $this->reset('name', 'description', 'url');
        unset($this->projects);
    }

    public function dismissCreatedBanner(): void
    {
        $this->lastCreatedProjectId = null;
        $this->lastCreatedCursorToken = null;
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
                <h1 class="text-3xl font-bold tracking-tight text-ink">Your projects</h1>
                <p class="mt-1 text-ink/70">Roadmaps, tasks, and bookmarks in one place.</p>
            </div>
            <a
                href="{{ route('dashboard') }}"
                wire:navigate
                class="text-sm font-medium text-sage-dark hover:text-sage-deeper"
            >
                ← Back to overview
            </a>
        </div>

        <div class="rounded-2xl border border-cream-300/80 bg-white p-6 shadow-sm ring-1 ring-ink/5">
            <h2 class="text-lg font-semibold text-ink">New project</h2>
            <p class="mt-1 text-sm text-ink/55">Give it a name; you can add tasks and links inside.</p>
            <form wire:submit="save" class="mt-6 space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-ink">Name</label>
                    <input
                        wire:model="name"
                        id="name"
                        type="text"
                        class="mt-1 block w-full rounded-lg border-cream-300 shadow-sm focus:border-sage focus:ring-sage"
                        placeholder="e.g. Launch landing page"
                        required
                    />
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-ink">Description <span class="text-ink/40 font-normal">(optional)</span></label>
                    <textarea
                        wire:model="description"
                        id="description"
                        rows="3"
                        class="mt-1 block w-full rounded-lg border-cream-300 shadow-sm focus:border-sage focus:ring-sage"
                        placeholder="What is this project about?"
                    ></textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="project-url" class="block text-sm font-medium text-ink">
                        Project URL <span class="text-ink/40 font-normal">(optional)</span>
                    </label>
                    <input
                        wire:model="url"
                        id="project-url"
                        type="url"
                        inputmode="url"
                        autocomplete="url"
                        class="mt-1 block w-full rounded-lg border-cream-300 shadow-sm focus:border-sage focus:ring-sage"
                        placeholder="https://…"
                    />
                    @error('url')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-lg bg-sage px-4 py-2 text-sm font-semibold text-white shadow hover:bg-sage-dark focus:outline-none focus:ring-2 focus:ring-sage focus:ring-offset-2"
                    >
                        Create project
                    </button>
                </div>
            </form>
            @if ($this->lastCreatedProjectId)
                <div
                    class="mt-6 rounded-xl border border-sage-light/60 bg-sage-light/10 p-4"
                    wire:key="created-banner-{{ $this->lastCreatedProjectId }}"
                >
                    <p class="text-sm font-medium text-ink">Project created.</p>
                    <p class="mt-1 text-sm text-ink/70">
                        Download <code class="rounded bg-cream-200 px-1 py-0.5 text-xs">waypost.json</code> into this repo’s root. A project API token was created — copy it below for
                        <code class="rounded bg-cream-200 px-1 py-0.5 text-xs">WAYPOST_API_TOKEN</code> or paste into <code class="rounded bg-cream-200 px-1 py-0.5 text-xs">waypost.json</code> as
                        <code class="rounded bg-cream-200 px-1 py-0.5 text-xs">api_token</code> locally (never commit).
                        The file includes <code class="rounded bg-cream-200 px-1 py-0.5 text-xs">x_waypost_source</code> so tools can tag AI/API edits in project activity.
                        On the project page, use <strong>Download Cursor setup (ZIP)</strong> for waypost.json + Cursor rules + README in one step.
                    </p>
                    @if ($this->lastCreatedCursorToken)
                        <code class="mt-3 block select-all break-all rounded bg-white p-2 text-xs text-ink ring-1 ring-cream-300">{{ $this->lastCreatedCursorToken }}</code>
                    @endif
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <a
                            href="{{ route('projects.waypost-manifest', ['project' => $this->lastCreatedProjectId]) }}"
                            download
                            class="inline-flex items-center rounded-lg bg-sage px-4 py-2 text-sm font-semibold text-white shadow hover:bg-sage-dark"
                        >
                            Download waypost.json
                        </a>
                        <button
                            type="button"
                            wire:click="dismissCreatedBanner"
                            class="text-sm font-medium text-ink/70 hover:text-ink"
                        >
                            Dismiss
                        </button>
                    </div>
                </div>
            @endif
        </div>

        <div>
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-lg font-semibold text-ink">All projects</h2>
                <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-ink/80">
                    <input type="checkbox" wire:model.live="showArchived" class="rounded border-cream-300 text-sage focus:ring-sage" />
                    Show archived
                </label>
            </div>
            @if ($this->projects->isEmpty())
                <div class="rounded-2xl border border-dashed border-cream-300 bg-cream-100/80 px-6 py-16 text-center">
                    <p class="text-ink/70">No projects yet. Create one above to get started.</p>
                </div>
            @else
                <ul class="space-y-3">
                    @foreach ($this->projects as $project)
                        <li
                            wire:key="project-{{ $project->id }}"
                            class="group flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 rounded-xl border border-cream-300 bg-white p-5 shadow-sm transition hover:border-sage-light/50 hover:shadow-md"
                        >
                            <a href="{{ route('projects.show', $project) }}" wire:navigate class="min-w-0 flex-1">
                                <h3 class="font-semibold text-ink group-hover:text-sage-dark">
                                    {{ $project->name }}
                                    @if ($project->user_id !== auth()->id())
                                        <span class="ms-2 text-xs font-normal text-ink/50">Shared</span>
                                    @endif
                                    @if ($project->archived_at)
                                        <span class="ms-2 text-xs font-normal text-amber-800">Archived</span>
                                    @endif
                                </h3>
                                @if ($project->description)
                                    <p class="mt-1 text-sm text-ink/70 line-clamp-2">{{ $project->description }}</p>
                                @endif
                                @if ($project->url)
                                    <p class="mt-1 text-xs">
                                        <span class="text-ink/55">URL:</span>
                                        <span class="text-sage-dark break-all">{{ $project->url }}</span>
                                    </p>
                                @endif
                                <p class="mt-2 text-xs text-ink/55">
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
                                    class="rounded-lg bg-cream-200 px-3 py-2 text-sm font-medium text-ink hover:bg-cream-300"
                                >
                                    Open
                                </a>
                                @can('delete', $project)
                                    <button
                                        type="button"
                                        wire:click="delete({{ $project->id }})"
                                        wire:confirm="Delete this project and all of its tasks and links?"
                                        class="rounded-lg px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
                                    >
                                        Delete
                                    </button>
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
