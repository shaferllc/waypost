<?php

use App\Models\Project;
use App\Models\ProjectLink;
use App\Models\RoadmapVersion;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\TaskLink;
use App\Models\WishlistItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')]
class extends Component
{
    use WithFileUploads;

    public int $projectId;

    public string $tab = 'board';

    public string $taskTitle = '';

    public string $taskBody = '';

    public string $newTaskStatus = 'backlog';

    public ?int $newTaskVersionId = null;

    public string $linkTitle = '';

    public string $linkUrl = '';

    public string $wishTitle = '';

    public string $wishNotes = '';

    public string $versionName = '';

    public string $versionTargetDate = '';

    public string $versionDescription = '';

    public ?int $editingVersionId = null;

    public string $editVersionName = '';

    public string $editVersionTargetDate = '';

    public string $editVersionReleasedAt = '';

    public string $editVersionDescription = '';

    public string $editVersionReleaseNotes = '';

    public ?int $focusedTaskId = null;

    public string $commentBody = '';

    /** @var mixed */
    public $attachmentFile = null;

    public ?int $linkTargetTaskId = null;

    public string $linkType = 'relates';

    public bool $editingProject = false;

    public string $editProjectName = '';

    public string $editProjectDescription = '';

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
            ->with([
                'tasks' => fn ($query) => $query->with(['version'])
                    ->withCount(['attachments', 'comments']),
                'links',
                'versions',
                'wishlistItems',
            ])
            ->firstOrFail();
    }

    #[Computed]
    public function focusedTask(): ?Task
    {
        if ($this->focusedTaskId === null) {
            return null;
        }

        return Task::query()
            ->where('project_id', $this->projectId)
            ->with([
                'version',
                'attachments',
                'comments.user',
                'linksAsSource.target',
                'linksAsTarget.source',
            ])
            ->find($this->focusedTaskId);
    }

    public function startEditProject(): void
    {
        $this->authorize('update', $this->project);
        $this->editProjectName = $this->project->name;
        $this->editProjectDescription = (string) ($this->project->description ?? '');
        $this->editingProject = true;
    }

    public function cancelEditProject(): void
    {
        $this->editingProject = false;
        $this->reset('editProjectName', 'editProjectDescription');
    }

    public function saveProject(): void
    {
        $this->authorize('update', $this->project);

        $validated = $this->validate([
            'editProjectName' => ['required', 'string', 'max:120'],
            'editProjectDescription' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->project->update([
            'name' => $validated['editProjectName'],
            'description' => $validated['editProjectDescription'] ?: null,
        ]);

        $this->editingProject = false;
        $this->reset('editProjectName', 'editProjectDescription');
        unset($this->project);
    }

    public function syncKanban(array $columns): void
    {
        $this->authorize('update', $this->project);

        $statuses = Task::KANBAN_STATUSES;
        $normalized = [];
        foreach ($statuses as $s) {
            $normalized[$s] = array_values(array_filter(
                array_map('intval', $columns[$s] ?? []),
                fn (int $id) => $id > 0
            ));
        }

        $flattened = collect($normalized)->flatten()->unique()->sort()->values();
        $expected = $this->project->tasks()->pluck('id')->sort()->values();

        if ($flattened->toArray() !== $expected->toArray()) {
            return;
        }

        foreach ($normalized as $status => $ids) {
            foreach ($ids as $position => $id) {
                Task::query()
                    ->where('id', $id)
                    ->where('project_id', $this->project->id)
                    ->update([
                        'status' => $status,
                        'position' => $position,
                    ]);
            }
        }

        unset($this->project);
    }

    public function addTask(): void
    {
        $this->authorize('update', $this->project);

        $validated = $this->validate([
            'taskTitle' => ['required', 'string', 'max:255'],
            'taskBody' => ['nullable', 'string', 'max:5000'],
            'newTaskStatus' => ['required', Rule::in(Task::KANBAN_STATUSES)],
            'newTaskVersionId' => ['nullable', 'integer', Rule::exists('roadmap_versions', 'id')->where('project_id', $this->projectId)],
        ]);

        $status = $validated['newTaskStatus'];
        $max = (int) $this->project->tasks()->where('status', $status)->max('position');

        $this->project->tasks()->create([
            'title' => $validated['taskTitle'],
            'body' => $validated['taskBody'] ?: null,
            'status' => $status,
            'position' => $max + 1,
            'version_id' => $validated['newTaskVersionId'] ?? null,
        ]);

        $this->reset('taskTitle', 'taskBody');
        unset($this->project);
    }

    public function deleteTask(Task $task): void
    {
        $this->assertTaskOnProject($task);
        $this->authorize('update', $this->project);
        if ($this->focusedTaskId === $task->id) {
            $this->closeTaskDetail();
        }
        $task->delete();
        unset($this->project);
    }

    public function openTaskDetail(int $taskId): void
    {
        $exists = Task::query()
            ->where('project_id', $this->projectId)
            ->whereKey($taskId)
            ->exists();

        if (! $exists) {
            return;
        }

        $this->focusedTaskId = $taskId;
        $this->reset('commentBody', 'attachmentFile', 'linkTargetTaskId');
        $this->linkType = 'relates';
        unset($this->focusedTask);
    }

    public function closeTaskDetail(): void
    {
        $this->focusedTaskId = null;
        $this->reset('commentBody', 'attachmentFile', 'linkTargetTaskId');
        $this->linkType = 'relates';
        unset($this->focusedTask);
    }

    public function addComment(): void
    {
        $task = $this->focusedTask;
        if (! $task) {
            return;
        }

        $this->authorize('update', $this->project);

        $validated = $this->validate([
            'commentBody' => ['required', 'string', 'max:5000'],
        ]);

        $task->comments()->create([
            'user_id' => auth()->id(),
            'body' => $validated['commentBody'],
        ]);

        $this->reset('commentBody');
        unset($this->focusedTask);
    }

    public function deleteComment(TaskComment $comment): void
    {
        $comment->load('task');
        $this->assertTaskOnProject($comment->task);
        $this->authorize('update', $this->project);

        if ($comment->user_id !== auth()->id()) {
            abort(403);
        }

        $comment->delete();
        unset($this->focusedTask);
    }

    public function uploadAttachment(): void
    {
        $task = $this->focusedTask;
        if (! $task) {
            return;
        }

        $this->authorize('update', $this->project);

        $this->validate([
            'attachmentFile' => ['required', 'file', 'max:12288'],
        ]);

        $path = $this->attachmentFile->store('task-attachments/'.$task->id, 'local');

        $task->attachments()->create([
            'user_id' => auth()->id(),
            'disk' => 'local',
            'path' => $path,
            'original_name' => $this->attachmentFile->getClientOriginalName(),
            'mime_type' => $this->attachmentFile->getMimeType(),
            'size' => $this->attachmentFile->getSize(),
        ]);

        $this->reset('attachmentFile');
        unset($this->focusedTask);
        unset($this->project);
    }

    public function deleteAttachment(TaskAttachment $attachment): void
    {
        $attachment->load('task');
        $this->assertTaskOnProject($attachment->task);
        $this->authorize('update', $this->project);

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        unset($this->focusedTask);
        unset($this->project);
    }

    public function addTaskLink(): void
    {
        $task = $this->focusedTask;
        if (! $task) {
            return;
        }

        $this->authorize('update', $this->project);

        $validated = $this->validate([
            'linkTargetTaskId' => ['required', 'integer', Rule::exists('tasks', 'id')->where('project_id', $this->projectId)],
            'linkType' => ['required', Rule::in([TaskLink::TYPE_RELATES, TaskLink::TYPE_BLOCKS])],
        ]);

        $targetId = (int) $validated['linkTargetTaskId'];

        if ($targetId === $task->id) {
            $this->addError('linkTargetTaskId', __('Choose a different task.'));

            return;
        }

        if ($validated['linkType'] === TaskLink::TYPE_RELATES) {
            $sourceId = min($task->id, $targetId);
            $targetIdOrdered = max($task->id, $targetId);
            TaskLink::query()->firstOrCreate(
                [
                    'source_task_id' => $sourceId,
                    'target_task_id' => $targetIdOrdered,
                    'type' => TaskLink::TYPE_RELATES,
                ],
                []
            );
        } else {
            TaskLink::query()->firstOrCreate(
                [
                    'source_task_id' => $task->id,
                    'target_task_id' => $targetId,
                    'type' => TaskLink::TYPE_BLOCKS,
                ],
                []
            );
        }

        $this->reset('linkTargetTaskId');
        $this->linkType = TaskLink::TYPE_RELATES;
        unset($this->focusedTask);
    }

    public function deleteTaskLink(TaskLink $taskLink): void
    {
        $taskLink->load(['source', 'target']);

        if ($taskLink->source->project_id !== $this->projectId
            || $taskLink->target->project_id !== $this->projectId) {
            abort(403);
        }

        $this->authorize('update', $this->project);
        $taskLink->delete();
        unset($this->focusedTask);
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

    public function addWishlist(): void
    {
        $this->authorize('update', $this->project);

        $validated = $this->validate([
            'wishTitle' => ['required', 'string', 'max:255'],
            'wishNotes' => ['nullable', 'string', 'max:5000'],
        ]);

        $max = (int) $this->project->wishlistItems()->max('sort_order');

        $this->project->wishlistItems()->create([
            'title' => $validated['wishTitle'],
            'notes' => $validated['wishNotes'] ?: null,
            'sort_order' => $max + 1,
        ]);

        $this->reset('wishTitle', 'wishNotes');
        unset($this->project);
    }

    public function deleteWishlist(WishlistItem $item): void
    {
        $this->assertWishlistOnProject($item);
        $this->authorize('update', $this->project);
        $item->delete();
        unset($this->project);
    }

    public function promoteWishlistToTask(WishlistItem $item): void
    {
        $this->assertWishlistOnProject($item);
        $this->authorize('update', $this->project);

        DB::transaction(function () use ($item) {
            $max = (int) $this->project->tasks()->where('status', 'backlog')->max('position');
            $this->project->tasks()->create([
                'title' => $item->title,
                'body' => $item->notes,
                'status' => 'backlog',
                'position' => $max + 1,
            ]);
            $item->delete();
        });

        unset($this->project);
    }

    public function addVersion(): void
    {
        $this->authorize('update', $this->project);

        $validated = $this->validate([
            'versionName' => ['required', 'string', 'max:120'],
            'versionTargetDate' => ['nullable', 'date'],
            'versionDescription' => ['nullable', 'string', 'max:5000'],
        ]);

        $max = (int) $this->project->versions()->max('sort_order');

        $this->project->versions()->create([
            'name' => $validated['versionName'],
            'target_date' => $validated['versionTargetDate'] ?: null,
            'description' => $validated['versionDescription'] ?: null,
            'sort_order' => $max + 1,
        ]);

        $this->reset('versionName', 'versionTargetDate', 'versionDescription');
        unset($this->project);
    }

    public function deleteVersion(RoadmapVersion $version): void
    {
        $this->assertVersionOnProject($version);
        $this->authorize('update', $this->project);
        $version->tasks()->update(['version_id' => null]);
        $version->delete();
        if ($this->editingVersionId === $version->id) {
            $this->cancelEditVersion();
        }
        unset($this->project);
    }

    public function shipVersion(RoadmapVersion $version): void
    {
        $this->assertVersionOnProject($version);
        $this->authorize('update', $this->project);
        $version->update(['released_at' => now()->toDateString()]);
        unset($this->project);
    }

    public function unshipVersion(RoadmapVersion $version): void
    {
        $this->assertVersionOnProject($version);
        $this->authorize('update', $this->project);
        $version->update(['released_at' => null]);
        unset($this->project);
    }

    public function startEditVersion(RoadmapVersion $version): void
    {
        $this->assertVersionOnProject($version);
        $this->authorize('update', $this->project);
        $this->editingVersionId = $version->id;
        $this->editVersionName = $version->name;
        $this->editVersionTargetDate = $version->target_date?->format('Y-m-d') ?? '';
        $this->editVersionReleasedAt = $version->released_at?->format('Y-m-d') ?? '';
        $this->editVersionDescription = $version->description ?? '';
        $this->editVersionReleaseNotes = $version->release_notes ?? '';
    }

    public function cancelEditVersion(): void
    {
        $this->editingVersionId = null;
    }

    public function saveEditVersion(): void
    {
        $version = RoadmapVersion::query()
            ->where('project_id', $this->project->id)
            ->findOrFail($this->editingVersionId);

        $this->authorize('update', $this->project);

        $validated = $this->validate([
            'editVersionName' => ['required', 'string', 'max:120'],
            'editVersionTargetDate' => ['nullable', 'date'],
            'editVersionReleasedAt' => ['nullable', 'date'],
            'editVersionDescription' => ['nullable', 'string', 'max:5000'],
            'editVersionReleaseNotes' => ['nullable', 'string', 'max:10000'],
        ]);

        $version->update([
            'name' => $validated['editVersionName'],
            'target_date' => $validated['editVersionTargetDate'] ?: null,
            'released_at' => $validated['editVersionReleasedAt'] ?: null,
            'description' => $validated['editVersionDescription'] ?: null,
            'release_notes' => $validated['editVersionReleaseNotes'] ?: null,
        ]);

        $this->editingVersionId = null;
        unset($this->project);
    }

    public function assignTaskToVersion(int $taskId, string $versionValue): void
    {
        $task = Task::query()
            ->where('project_id', $this->project->id)
            ->find($taskId);

        if (! $task) {
            return;
        }

        $this->authorize('update', $this->project);

        $vid = $versionValue !== '' ? (int) $versionValue : null;

        if ($vid) {
            $exists = RoadmapVersion::query()
                ->where('project_id', $this->project->id)
                ->whereKey($vid)
                ->exists();
            if (! $exists) {
                return;
            }
        }

        $task->update(['version_id' => $vid]);
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

    private function assertWishlistOnProject(WishlistItem $item): void
    {
        if ($item->project_id !== $this->project->id) {
            abort(403);
        }
    }

    private function assertVersionOnProject(RoadmapVersion $version): void
    {
        if ($version->project_id !== $this->project->id) {
            abort(403);
        }
    }
}; ?>

@php
    $kanbanLabels = [
        'backlog' => 'Backlog',
        'todo' => 'Ready',
        'in_progress' => 'In progress',
        'done' => 'Done',
    ];
    $kanbanHints = [
        'backlog' => 'Ideas & parking lot',
        'todo' => 'Ready to pick up',
        'in_progress' => 'Active work',
        'done' => 'Shipped in the board',
    ];
@endphp

<div class="py-10 px-4 sm:px-6 lg:px-8">
    <div class="max-w-6xl mx-auto space-y-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 flex-1">
                <a
                    href="{{ route('projects.index') }}"
                    wire:navigate
                    class="text-sm font-medium text-sage-dark hover:text-sage-deeper"
                >
                    ← All projects
                </a>
                @if ($this->editingProject)
                    <form wire:submit="saveProject" class="mt-3 max-w-2xl space-y-4">
                        <div>
                            <label for="editProjectName" class="block text-sm font-medium text-ink">Name</label>
                            <input
                                wire:model="editProjectName"
                                id="editProjectName"
                                type="text"
                                class="mt-1 block w-full rounded-lg border-cream-300 shadow-sm focus:border-sage focus:ring-sage"
                                required
                            />
                            @error('editProjectName')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="editProjectDescription" class="block text-sm font-medium text-ink">
                                Description <span class="text-ink/40 font-normal">(optional)</span>
                            </label>
                            <textarea
                                wire:model="editProjectDescription"
                                id="editProjectDescription"
                                rows="3"
                                class="mt-1 block w-full rounded-lg border-cream-300 shadow-sm focus:border-sage focus:ring-sage"
                            ></textarea>
                            @error('editProjectDescription')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="submit"
                                class="inline-flex items-center rounded-lg bg-sage px-4 py-2 text-sm font-semibold text-white shadow hover:bg-sage-dark focus:outline-none focus:ring-2 focus:ring-sage focus:ring-offset-2"
                            >
                                Save changes
                            </button>
                            <button
                                type="button"
                                wire:click="cancelEditProject"
                                class="inline-flex items-center rounded-lg border border-cream-300 bg-cream-50 px-4 py-2 text-sm font-semibold text-ink shadow-sm hover:bg-cream-100"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                @else
                    <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h1 class="text-3xl font-bold tracking-tight text-ink">{{ $this->project->name }}</h1>
                            @if ($this->project->description)
                                <p class="mt-2 text-ink/70 max-w-2xl">{{ $this->project->description }}</p>
                            @endif
                        </div>
                        @can('update', $this->project)
                            <button
                                type="button"
                                wire:click="startEditProject"
                                class="shrink-0 rounded-lg border border-cream-300 bg-cream-50 px-3 py-2 text-sm font-semibold text-ink shadow-sm hover:bg-cream-100"
                            >
                                Edit project
                            </button>
                        @endcan
                    </div>
                @endif
            </div>
        </div>

        <div class="border-b border-cream-300">
            <nav class="-mb-px flex gap-1 overflow-x-auto pb-px" aria-label="Project sections">
                @foreach (['board' => 'Board', 'roadmap' => 'Roadmap', 'wishlist' => 'Wishlist', 'links' => 'Links'] as $key => $label)
                    <button
                        type="button"
                        wire:click="$set('tab', '{{ $key }}')"
                        class="shrink-0 rounded-t-lg px-4 py-2.5 text-sm font-semibold transition
                            {{ $this->tab === $key
                                ? 'bg-white text-sage-deeper ring-1 ring-b-0 ring-cream-300'
                                : 'text-ink/70 hover:text-ink hover:bg-cream-100' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- Kanban --}}
        @if ($this->tab === 'board')
            <div class="space-y-6" wire:key="tab-board">
                <div
                    data-kanban-board
                    class="overflow-x-auto pb-2 -mx-1 px-1"
                >
                    <div class="flex min-h-[28rem] gap-4" style="min-width: min(100%, 56rem);">
                        @foreach (Task::KANBAN_STATUSES as $status)
                            @php
                                $colTasks = $this->project->tasks
                                    ->filter(fn (Task $t) => $t->status === $status)
                                    ->sortBy('position')
                                    ->values();
                            @endphp
                            <div class="flex w-72 shrink-0 flex-col rounded-xl border border-cream-300 bg-cream-100/80 shadow-sm">
                                <div class="border-b border-cream-300/80 px-3 py-2">
                                    <h3 class="text-sm font-semibold text-ink">{{ $kanbanLabels[$status] }}</h3>
                                    <p class="text-xs text-ink/55">{{ $kanbanHints[$status] }}</p>
                                </div>
                                <ul
                                    class="flex flex-1 flex-col gap-2 p-2"
                                    data-kanban-list
                                    data-kanban-status="{{ $status }}"
                                >
                                    @foreach ($colTasks as $task)
                                        <li
                                            wire:key="kanban-task-{{ $task->id }}"
                                            data-task-id="{{ $task->id }}"
                                            class="flex gap-2 rounded-lg border border-cream-300 bg-white p-2 shadow-sm"
                                        >
                                            <span
                                                class="kanban-card-handle cursor-grab select-none rounded px-0.5 py-1 text-ink/40 hover:bg-cream-200 active:cursor-grabbing"
                                                title="Drag to move"
                                            >⋮⋮</span>
                                            <div class="min-w-0 flex-1">
                                                <p class="font-medium text-ink text-sm">{{ $task->title }}</p>
                                                @if ($task->body)
                                                    <p class="mt-1 text-xs text-ink/70 line-clamp-3">{{ $task->body }}</p>
                                                @endif
                                                @if ($task->version)
                                                    <p class="mt-2 text-[10px] font-semibold uppercase tracking-wide text-sage-dark">
                                                        {{ $task->version->name }}
                                                    </p>
                                                @endif
                                                <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px] text-ink/55">
                                                    @if ($task->comments_count > 0)
                                                        <span>{{ $task->comments_count }} {{ $task->comments_count === 1 ? 'comment' : 'comments' }}</span>
                                                    @endif
                                                    @if ($task->attachments_count > 0)
                                                        <span>{{ $task->attachments_count }} {{ $task->attachments_count === 1 ? 'file' : 'files' }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                wire:click.stop="openTaskDetail({{ $task->id }})"
                                                class="shrink-0 self-start rounded-md px-2 py-1 text-xs font-semibold text-sage-dark hover:bg-sage-light/10"
                                            >
                                                Open
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>

                <section class="rounded-2xl border border-cream-300/80 bg-white p-6 shadow-sm ring-1 ring-ink/5">
                    <h2 class="text-lg font-semibold text-ink">Add card</h2>
                    <p class="mt-1 text-sm text-ink/55">New tasks start in a column of your choice. Optionally tie them to a roadmap version.</p>
                    <form wire:submit="addTask" class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="taskTitle" class="block text-xs font-medium text-ink/70">Title</label>
                            <input
                                wire:model="taskTitle"
                                id="taskTitle"
                                type="text"
                                class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm focus:border-sage focus:ring-sage"
                                required
                            />
                            @error('taskTitle')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="taskBody" class="block text-xs font-medium text-ink/70">Notes</label>
                            <textarea
                                wire:model="taskBody"
                                id="taskBody"
                                rows="2"
                                class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm focus:border-sage focus:ring-sage"
                            ></textarea>
                            @error('taskBody')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="newTaskStatus" class="block text-xs font-medium text-ink/70">Column</label>
                            <select
                                wire:model="newTaskStatus"
                                id="newTaskStatus"
                                class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm focus:border-sage focus:ring-sage"
                            >
                                @foreach (Task::KANBAN_STATUSES as $st)
                                    <option value="{{ $st }}">{{ $kanbanLabels[$st] }}</option>
                                @endforeach
                            </select>
                            @error('newTaskStatus')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="newTaskVersionId" class="block text-xs font-medium text-ink/70">Roadmap version <span class="font-normal text-ink/40">(optional)</span></label>
                            <select
                                wire:model.live="newTaskVersionId"
                                id="newTaskVersionId"
                                class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm focus:border-sage focus:ring-sage"
                            >
                                <option value="">— None —</option>
                                @foreach ($this->project->versions as $v)
                                    <option value="{{ $v->id }}">{{ $v->name }}</option>
                                @endforeach
                            </select>
                            @error('newTaskVersionId')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex items-end justify-end sm:col-span-2">
                            <button
                                type="submit"
                                class="rounded-lg bg-sage px-4 py-2 text-sm font-semibold text-white shadow hover:bg-sage-dark"
                            >
                                Add to board
                            </button>
                        </div>
                    </form>
                </section>

                <section class="rounded-2xl border border-cream-300/80 bg-white p-6 shadow-sm ring-1 ring-ink/5">
                    <h2 class="text-lg font-semibold text-ink">All tasks</h2>
                    <p class="mt-1 text-sm text-ink/55">Remove a card from the project (same as deleting it from the board).</p>
                    <ul class="mt-4 divide-y divide-cream-200">
                        @foreach ($this->project->tasks->sortBy(fn (Task $t) => [$t->status, $t->position]) as $task)
                            <li wire:key="task-row-{{ $task->id }}" class="flex flex-wrap items-center justify-between gap-2 py-3">
                                <div class="min-w-0">
                                    <span class="text-sm font-medium text-ink">{{ $task->title }}</span>
                                    <span class="ms-2 text-xs text-ink/55">{{ $kanbanLabels[$task->status] ?? $task->status }}</span>
                                </div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <button
                                        type="button"
                                        wire:click="openTaskDetail({{ $task->id }})"
                                        class="text-sm font-medium text-sage-dark hover:text-sage-deeper"
                                    >
                                        Details
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="deleteTask({{ $task->id }})"
                                        wire:confirm="Delete this task?"
                                        class="text-sm text-red-600 hover:text-red-700"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>
        @endif

        {{-- Roadmap --}}
        @if ($this->tab === 'roadmap')
            <div class="space-y-8" wire:key="tab-roadmap">
                <section class="rounded-2xl border border-cream-300/80 bg-white p-6 shadow-sm ring-1 ring-ink/5">
                    <h2 class="text-lg font-semibold text-ink">Versions & milestones</h2>
                    <p class="mt-1 text-sm text-ink/55">Plan releases, target dates, and changelog-style notes. Assign tasks to a version below.</p>

                    <form wire:submit="addVersion" class="mt-6 grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-ink/70">Version name</label>
                            <input
                                wire:model="versionName"
                                type="text"
                                class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm focus:border-sage focus:ring-sage"
                                placeholder="e.g. 1.0, MVP, March drop"
                                required
                            />
                            @error('versionName')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-ink/70">Target date</label>
                            <input
                                wire:model="versionTargetDate"
                                type="date"
                                class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm focus:border-sage focus:ring-sage"
                            />
                            @error('versionTargetDate')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-ink/70">Goals / scope</label>
                            <textarea
                                wire:model="versionDescription"
                                rows="2"
                                class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm focus:border-sage focus:ring-sage"
                                placeholder="What ships in this version?"
                            ></textarea>
                        </div>
                        <div class="sm:col-span-2 flex justify-end">
                            <button type="submit" class="rounded-lg bg-sage px-4 py-2 text-sm font-semibold text-white shadow hover:bg-sage-dark">
                                Add version
                            </button>
                        </div>
                    </form>
                </section>

                @if ($editingVersionId)
                    @php
                        $editing = $this->project->versions->firstWhere('id', $editingVersionId);
                    @endphp
                    @if ($editing)
                        <section class="rounded-2xl border-2 border-sage-light/60 bg-sage-light/10 p-6 shadow-sm" wire:key="edit-version-{{ $editingVersionId }}">
                            <div class="flex items-start justify-between gap-4">
                                <h3 class="text-lg font-semibold text-ink">Edit version</h3>
                                <button type="button" wire:click="cancelEditVersion" class="text-sm font-medium text-ink/70 hover:text-ink">Cancel</button>
                            </div>
                            <form wire:submit="saveEditVersion" class="mt-4 space-y-3">
                                <div>
                                    <label class="block text-xs font-medium text-ink/70">Name</label>
                                    <input wire:model="editVersionName" type="text" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm focus:border-sage focus:ring-sage" required />
                                    @error('editVersionName')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-xs font-medium text-ink/70">Target date</label>
                                        <input wire:model="editVersionTargetDate" type="date" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-ink/70">Released on</label>
                                        <input wire:model="editVersionReleasedAt" type="date" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm" />
                                        <p class="mt-1 text-xs text-ink/55">Set when this version shipped (changelog).</p>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-ink/70">Goals / scope</label>
                                    <textarea wire:model="editVersionDescription" rows="2" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm"></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-ink/70">Release notes</label>
                                    <textarea wire:model="editVersionReleaseNotes" rows="4" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm" placeholder="What changed? Bullet list is fine."></textarea>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <button type="submit" class="rounded-lg bg-sage px-4 py-2 text-sm font-semibold text-white shadow hover:bg-sage-dark">
                                        Save version
                                    </button>
                                </div>
                            </form>
                        </section>
                    @endif
                @endif

                <div class="relative space-y-6 pl-4 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-px before:bg-cream-300">
                    @forelse ($this->project->versions as $version)
                        <article wire:key="version-{{ $version->id }}" class="relative rounded-2xl border border-cream-300 bg-white p-5 shadow-sm pl-6">
                            <span class="absolute -left-[1.15rem] top-6 flex h-3 w-3 rounded-full border-2 border-white bg-sage-light ring-2 ring-cream-300"></span>
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-semibold text-ink">{{ $version->name }}</h3>
                                    <div class="mt-1 flex flex-wrap gap-2 text-xs text-ink/70">
                                        @if ($version->target_date)
                                            <span class="rounded-full hover:bg-cream-200 px-2 py-0.5 font-medium">Target {{ $version->target_date->format('M j, Y') }}</span>
                                        @endif
                                        @if ($version->released_at)
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-900">Released {{ $version->released_at->format('M j, Y') }}</span>
                                        @else
                                            <span class="rounded-full bg-amber-50 px-2 py-0.5 font-medium text-amber-900">Not released</span>
                                        @endif
                                    </div>
                                    @if ($version->description)
                                        <p class="mt-3 text-sm text-ink/70">{{ $version->description }}</p>
                                    @endif
                                    @if ($version->release_notes && $version->released_at)
                                        <div class="mt-4 rounded-lg bg-cream-100 p-3 text-sm text-ink whitespace-pre-wrap">{{ $version->release_notes }}</div>
                                    @endif
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @if (! $version->released_at)
                                        <button
                                            type="button"
                                            wire:click="shipVersion({{ $version->id }})"
                                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow hover:bg-emerald-500"
                                        >
                                            Mark shipped today
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            wire:click="unshipVersion({{ $version->id }})"
                                            class="rounded-lg border border-cream-300 px-3 py-1.5 text-xs font-semibold text-ink hover:bg-cream-100"
                                        >
                                            Clear release date
                                        </button>
                                    @endif
                                    <button type="button" wire:click="startEditVersion({{ $version->id }})" class="rounded-lg border border-cream-300 px-3 py-1.5 text-xs font-semibold text-ink hover:bg-cream-100">
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="deleteVersion({{ $version->id }})"
                                        wire:confirm="Delete this version? Tasks stay but lose this assignment."
                                        class="rounded-lg px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                            @php $vTasks = $this->project->tasks->where('version_id', $version->id)->sortBy('position'); @endphp
                            @if ($vTasks->isNotEmpty())
                                <ul class="mt-4 space-y-1 border-t border-cream-200 pt-4">
                                    @foreach ($vTasks as $vt)
                                        <li class="text-sm text-ink">• {{ $vt->title }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </article>
                    @empty
                        <p class="text-sm text-ink/55 pl-4">No versions yet. Add one to build your roadmap timeline.</p>
                    @endforelse
                </div>

                <section class="rounded-2xl border border-cream-300/80 bg-white p-6 shadow-sm ring-1 ring-ink/5">
                    <h2 class="text-lg font-semibold text-ink">Assign tasks to versions</h2>
                    <p class="mt-1 text-sm text-ink/55">Connect board work to planned releases.</p>
                    <ul class="mt-4 space-y-3">
                        @foreach ($this->project->tasks as $task)
                            <li wire:key="assign-{{ $task->id }}" class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between" x-data>
                                <span class="text-sm font-medium text-ink">{{ $task->title }}</span>
                                <select
                                    x-on:change="$wire.assignTaskToVersion({{ $task->id }}, $event.target.value)"
                                    class="rounded-lg border-cream-300 text-sm shadow-sm focus:border-sage focus:ring-sage sm:max-w-xs sm:min-w-[12rem]"
                                >
                                    <option value="" @selected($task->version_id === null)>Unscheduled</option>
                                    @foreach ($this->project->versions as $v)
                                        <option value="{{ $v->id }}" @selected($task->version_id === $v->id)>{{ $v->name }}</option>
                                    @endforeach
                                </select>
                            </li>
                        @endforeach
                    </ul>
                    @if ($this->project->tasks->isEmpty())
                        <p class="mt-2 text-sm text-ink/55">Add tasks on the Board tab first.</p>
                    @endif
                </section>
            </div>
        @endif

        {{-- Wishlist --}}
        @if ($this->tab === 'wishlist')
            <div class="space-y-6" wire:key="tab-wishlist">
                <section class="rounded-2xl border border-cream-300/80 bg-white p-6 shadow-sm ring-1 ring-ink/5">
                    <h2 class="text-lg font-semibold text-ink">Wishlist & ideas</h2>
                    <p class="mt-1 text-sm text-ink/55">Capture “maybe later” items. Promote any idea to a backlog card when you are ready to build it.</p>
                    <form wire:submit="addWishlist" class="mt-6 space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-ink/70">Idea</label>
                            <input wire:model="wishTitle" type="text" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm focus:border-sage focus:ring-sage" required />
                            @error('wishTitle')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-ink/70">Notes</label>
                            <textarea wire:model="wishNotes" rows="2" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm"></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="rounded-lg bg-sage px-4 py-2 text-sm font-semibold text-white shadow hover:bg-sage-dark">Add idea</button>
                        </div>
                    </form>
                </section>

                <ul class="space-y-3">
                    @forelse ($this->project->wishlistItems as $item)
                        <li
                            wire:key="wish-{{ $item->id }}"
                            class="flex flex-col gap-3 rounded-xl border border-cream-300 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="min-w-0">
                                <p class="font-medium text-ink">{{ $item->title }}</p>
                                @if ($item->notes)
                                    <p class="mt-1 text-sm text-ink/70">{{ $item->notes }}</p>
                                @endif
                            </div>
                            <div class="flex shrink-0 flex-wrap gap-2">
                                <button
                                    type="button"
                                    wire:click="promoteWishlistToTask({{ $item->id }})"
                                    class="rounded-lg bg-sage px-3 py-2 text-sm font-semibold text-white shadow hover:bg-sage-dark"
                                >
                                    Promote to task
                                </button>
                                <button
                                    type="button"
                                    wire:click="deleteWishlist({{ $item->id }})"
                                    wire:confirm="Remove this idea?"
                                    class="rounded-lg px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
                                >
                                    Remove
                                </button>
                            </div>
                        </li>
                    @empty
                        <li class="rounded-xl border border-dashed border-cream-300 bg-cream-100/80 py-12 text-center text-sm text-ink/70">The wishlist is empty.</li>
                    @endforelse
                </ul>
            </div>
        @endif

        {{-- Links --}}
        @if ($this->tab === 'links')
            <div class="max-w-xl space-y-6" wire:key="tab-links">
                <section class="rounded-2xl border border-cream-300/80 bg-white p-6 shadow-sm ring-1 ring-ink/5">
                    <h2 class="text-lg font-semibold text-ink">Links</h2>
                    <p class="mt-1 text-sm text-ink/55">Repos, docs, designs, trackers.</p>
                    <form wire:submit="addLink" class="mt-6 space-y-3">
                        <div>
                            <label for="linkTitle" class="block text-xs font-medium text-ink/70">Label</label>
                            <input wire:model="linkTitle" id="linkTitle" type="text" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm focus:border-sage focus:ring-sage" required />
                            @error('linkTitle')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="linkUrl" class="block text-xs font-medium text-ink/70">URL</label>
                            <input wire:model="linkUrl" id="linkUrl" type="url" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm focus:border-sage focus:ring-sage" required />
                            @error('linkUrl')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="rounded-lg bg-ink px-4 py-2 text-sm font-semibold text-cream-50 shadow hover:bg-umber">Add link</button>
                        </div>
                    </form>
                    <ul class="mt-6 space-y-2">
                        @forelse ($this->project->links as $link)
                            <li wire:key="link-{{ $link->id }}" class="flex items-start justify-between gap-2 rounded-lg border border-cream-200 bg-cream-100/80 px-3 py-2">
                                <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer" class="min-w-0 text-sm font-medium text-sage-dark hover:underline">
                                    {{ $link->title }}
                                </a>
                                <button type="button" wire:click="deleteLink({{ $link->id }})" wire:confirm="Remove this link?" class="shrink-0 text-xs text-ink/55 hover:text-red-600">×</button>
                            </li>
                        @empty
                            <li class="py-6 text-center text-sm text-ink/55">No links yet.</li>
                        @endforelse
                    </ul>
                </section>
            </div>
        @endif
    </div>

    @if ($this->focusedTask)
        @php $ft = $this->focusedTask; @endphp
        <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true">
            <div
                class="absolute inset-0 bg-ink/50 backdrop-blur-sm"
                wire:click="closeTaskDetail"
            ></div>
            <div class="relative flex max-h-[90vh] w-full max-w-lg flex-col rounded-2xl border border-cream-300 bg-white shadow-xl">
                <div class="flex items-start justify-between gap-4 border-b border-cream-200 px-5 py-4">
                    <div class="min-w-0">
                        <h2 class="text-lg font-bold text-ink">{{ $ft->title }}</h2>
                        @if ($ft->version)
                            <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-sage-dark">{{ $ft->version->name }}</p>
                        @endif
                    </div>
                    <button
                        type="button"
                        wire:click="closeTaskDetail"
                        class="rounded-lg p-2 text-ink/55 hover:bg-cream-200 hover:text-ink"
                    >
                        <span class="sr-only">Close</span>
                        ×
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-6">
                    @if ($ft->body)
                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-ink/55">Description</h3>
                            <p class="mt-1 text-sm text-ink whitespace-pre-wrap">{{ $ft->body }}</p>
                        </div>
                    @endif

                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-ink/55">Linked tasks</h3>
                        <ul class="mt-2 space-y-2 text-sm">
                            @foreach ($ft->linksAsSource as $link)
                                @if ($link->type === TaskLink::TYPE_RELATES)
                                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-cream-100 px-3 py-2">
                                        <span><span class="text-ink/55">Related to</span>
                                            <button type="button" wire:click="openTaskDetail({{ $link->target_id }})" class="ms-1 font-medium text-sage-dark hover:underline">{{ $link->target->title }}</button>
                                        </span>
                                        <button type="button" wire:click="deleteTaskLink({{ $link->id }})" class="text-xs text-red-600 hover:underline">Remove</button>
                                    </li>
                                @else
                                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-amber-50/80 px-3 py-2">
                                        <span><span class="text-ink/70">Blocks</span>
                                            <button type="button" wire:click="openTaskDetail({{ $link->target_id }})" class="ms-1 font-medium text-sage-dark hover:underline">{{ $link->target->title }}</button>
                                        </span>
                                        <button type="button" wire:click="deleteTaskLink({{ $link->id }})" class="text-xs text-red-600 hover:underline">Remove</button>
                                    </li>
                                @endif
                            @endforeach
                            @foreach ($ft->linksAsTarget as $link)
                                @if ($link->type === TaskLink::TYPE_RELATES)
                                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-cream-100 px-3 py-2">
                                        <span><span class="text-ink/55">Related to</span>
                                            <button type="button" wire:click="openTaskDetail({{ $link->source_id }})" class="ms-1 font-medium text-sage-dark hover:underline">{{ $link->source->title }}</button>
                                        </span>
                                        <button type="button" wire:click="deleteTaskLink({{ $link->id }})" class="text-xs text-red-600 hover:underline">Remove</button>
                                    </li>
                                @else
                                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-red-50/60 px-3 py-2">
                                        <span><span class="text-ink/70">Blocked by</span>
                                            <button type="button" wire:click="openTaskDetail({{ $link->source_id }})" class="ms-1 font-medium text-sage-dark hover:underline">{{ $link->source->title }}</button>
                                        </span>
                                        <button type="button" wire:click="deleteTaskLink({{ $link->id }})" class="text-xs text-red-600 hover:underline">Remove</button>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                        @if ($ft->linksAsSource->isEmpty() && $ft->linksAsTarget->isEmpty())
                            <p class="mt-1 text-sm text-ink/55">No links yet.</p>
                        @endif

                        <form wire:submit="addTaskLink" class="mt-3 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end">
                            <div class="min-w-0 flex-1">
                                <label class="sr-only">Other task</label>
                                <select
                                    wire:model="linkTargetTaskId"
                                    class="block w-full rounded-lg border-cream-300 text-sm shadow-sm focus:border-sage focus:ring-sage"
                                >
                                    <option value="">Choose task…</option>
                                    @foreach ($this->project->tasks as $ot)
                                        @if ($ot->id !== $ft->id)
                                            <option value="{{ $ot->id }}">{{ $ot->title }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @error('linkTargetTaskId')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="sr-only">Link type</label>
                                <select wire:model="linkType" class="block w-full rounded-lg border-cream-300 text-sm shadow-sm sm:w-40">
                                    <option value="{{ TaskLink::TYPE_RELATES }}">Related</option>
                                    <option value="{{ TaskLink::TYPE_BLOCKS }}">This blocks that</option>
                                </select>
                            </div>
                            <button type="submit" class="rounded-lg bg-ink px-3 py-2 text-sm font-semibold text-cream-50 hover:bg-umber">Add link</button>
                        </form>
                    </div>

                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-ink/55">Attachments</h3>
                        <ul class="mt-2 space-y-2">
                            @forelse ($ft->attachments as $att)
                                <li wire:key="att-{{ $att->id }}" class="flex items-center justify-between gap-2 rounded-lg border border-cream-200 bg-cream-100 px-3 py-2 text-sm">
                                    <a href="{{ route('task-attachments.download', $att) }}" class="min-w-0 truncate font-medium text-sage-dark hover:underline">
                                        {{ $att->original_name }}
                                    </a>
                                    <span class="shrink-0 text-xs text-ink/55">{{ \Illuminate\Support\Number::fileSize($att->size) }}</span>
                                    <button
                                        type="button"
                                        wire:click="deleteAttachment({{ $att->id }})"
                                        wire:confirm="Delete this file?"
                                        class="shrink-0 text-xs text-red-600 hover:underline"
                                    >
                                        Delete
                                    </button>
                                </li>
                            @empty
                                <li class="text-sm text-ink/55">No files yet.</li>
                            @endforelse
                        </ul>
                        <form wire:submit="uploadAttachment" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                            <input
                                type="file"
                                wire:model="attachmentFile"
                                class="block w-full text-sm text-ink/70 file:mr-3 file:rounded-lg file:border-0 file:bg-sage-light/15 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-sage-deeper hover:file:bg-sage-light/25"
                            />
                            <button type="submit" class="rounded-lg bg-sage px-3 py-2 text-sm font-semibold text-white hover:bg-sage-dark">Upload</button>
                        </form>
                        @error('attachmentFile')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                        <div wire:loading wire:target="attachmentFile" class="mt-1 text-xs text-ink/55">Preparing file…</div>
                    </div>

                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-ink/55">Comments</h3>
                        <ul class="mt-2 space-y-3">
                            @forelse ($ft->comments as $comment)
                                <li wire:key="comment-{{ $comment->id }}" class="rounded-lg border border-cream-200 bg-cream-100/80 px-3 py-2">
                                    <div class="flex items-center justify-between gap-2 text-xs text-ink/55">
                                        <span class="font-medium text-ink">{{ $comment->user->name }}</span>
                                        <span>{{ $comment->created_at->diffForHumans() }}</span>
                                    </div>
                                    <p class="mt-1 text-sm text-ink whitespace-pre-wrap">{{ $comment->body }}</p>
                                    @if ($comment->user_id === auth()->id())
                                        <button
                                            type="button"
                                            wire:click="deleteComment({{ $comment->id }})"
                                            wire:confirm="Delete this comment?"
                                            class="mt-2 text-xs text-red-600 hover:underline"
                                        >
                                            Delete
                                        </button>
                                    @endif
                                </li>
                            @empty
                                <li class="text-sm text-ink/55">No comments yet.</li>
                            @endforelse
                        </ul>
                        <form wire:submit="addComment" class="mt-3 space-y-2">
                            <textarea
                                wire:model="commentBody"
                                rows="2"
                                class="block w-full rounded-lg border-cream-300 text-sm shadow-sm focus:border-sage focus:ring-sage"
                                placeholder="Write a comment…"
                            ></textarea>
                            @error('commentBody')
                                <p class="text-xs text-red-600">{{ $message }}</p>
                            @enderror
                            <button type="submit" class="rounded-lg bg-sage px-3 py-2 text-sm font-semibold text-white hover:bg-sage-dark">Post comment</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
