<?php

use App\Models\OkrGoal;
use App\Models\OkrKeyResult;
use App\Models\OkrObjective;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\ProjectLink;
use App\Models\ProjectShareToken;
use App\Models\ProjectWebhook;
use App\Models\RoadmapTheme;
use App\Models\RoadmapVersion;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\TaskLink;
use App\Models\WishlistItem;
use App\Services\ProjectCursorTokenIssuer;
use App\Support\WaypostCursorArtifacts;
use App\Support\WaypostEditorMcpInstall;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

    public string $editProjectUrl = '';

    public string $boardSearch = '';

    public string $boardLayout = 'columns';

    public bool $hideShippedVersions = false;

    public string $inviteEmail = '';

    public string $inviteRole = 'editor';

    public string $webhookUrl = '';

    public string $webhookEvents = '';

    public string $shareLinkLabel = '';

    public string $themeName = '';

    public string $themeColor = '#0d9488';

    public int $editTaskPriority = 2;

    public string $editTaskDueDate = '';

    public string $editTaskTags = '';

    public ?int $editTaskAssigneeId = null;

    public ?int $editTaskThemeId = null;

    public string $editTaskOkrObjectiveId = '';

    public string $editTaskStartsAt = '';

    public string $editTaskEndsAt = '';

    public string $editTaskPlanningStatus = '';

    public string $roadmapPlanView = 'list';

    public string $roadmapFilterTag = '';

    public string $roadmapFilterPlanning = '';

    public string $roadmapSort = 'title';

    public int $roadmapPlanPage = 1;

    public int $roadmapPlanPerPage = 25;

    public string $okrNewGoalTitle = '';

    public string $okrNewObjectiveTitle = '';

    public string $okrNewKrTitle = '';

    public int $okrNewKrProgress = 0;

    public ?string $revealedCursorToken = null;

    public bool $hideRevealedCursorToken = false;

    public bool $showCursorMcpEmbeddedNotice = false;

    /** @var 'cursor'|'vscode'|'vscode_insiders'|'other' */
    public string $syncEditor = 'cursor';

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->projectId = $project->id;

        $issuer = app(ProjectCursorTokenIssuer::class);
        if (Gate::allows('update', $project) && ! $issuer->hasTokenForProject(auth()->user(), $project)) {
            $this->revealedCursorToken = $issuer->issue($project, auth()->user());
            WaypostCursorArtifacts::flashCursorSetupToken($project->id, $this->revealedCursorToken);
            $this->hideRevealedCursorToken = false;
        }
    }

    public function updatedRoadmapFilterTag(): void
    {
        $this->roadmapPlanPage = 1;
    }

    public function updatedRoadmapFilterPlanning(): void
    {
        $this->roadmapPlanPage = 1;
    }

    public function updatedRoadmapSort(): void
    {
        $this->roadmapPlanPage = 1;
    }

    public function updatedRoadmapPlanPerPage(): void
    {
        $this->roadmapPlanPage = 1;
    }

    public function gotoRoadmapPlanPage(int $page): void
    {
        $last = $this->roadmapPlanTasksLastPage;
        $this->roadmapPlanPage = max(1, min($last, $page));
    }

    #[Computed]
    public function project(): Project
    {
        return Project::query()
            ->accessible(auth()->user())
            ->whereKey($this->projectId)
            ->with([
                'user',
                'members',
                'themes',
                'invitations',
                'shareTokens',
                'webhooks',
                'okrGoals' => fn ($query) => $query->with(['objectives.keyResults']),
                'tasks' => fn ($query) => $query
                    ->with([
                        'version',
                        'theme',
                        'assignee',
                        'okrObjective.goal',
                        'linksAsTarget' => fn ($q) => $q->where('type', TaskLink::TYPE_BLOCKS)->with('source:id,title,project_id'),
                    ])
                    ->withCount(['attachments', 'comments'])
                    ->withCount([
                        'linksAsTarget as blocking_links_count' => fn ($q) => $q->where('type', TaskLink::TYPE_BLOCKS),
                    ]),
                'activities' => fn ($query) => $query->with('user')->latest('id')->limit(50),
                'links',
                'versions',
                'wishlistItems',
            ])
            ->firstOrFail();
    }

    #[Computed]
    public function syncMcpCopySnippet(): string
    {
        return match ($this->syncEditor) {
            'vscode', 'vscode_insiders' => WaypostEditorMcpInstall::vscodeMcpJsonSnippet($this->project),
            default => WaypostCursorArtifacts::mcpServersSnippetJson($this->project),
        };
    }

    #[Computed]
    public function syncMcpInstallHref(): ?string
    {
        return match ($this->syncEditor) {
            'vscode' => WaypostEditorMcpInstall::vscodeMcpInstallUrl($this->project, false),
            'vscode_insiders' => WaypostEditorMcpInstall::vscodeMcpInstallUrl($this->project, true),
            default => null,
        };
    }

    #[Computed]
    public function syncInstallButtonLabel(): string
    {
        return match ($this->syncEditor) {
            'cursor' => 'Cursor',
            'vscode' => 'VS Code',
            'vscode_insiders' => 'VS Code Insiders',
            default => '',
        };
    }

    #[Computed]
    public function boardTasks()
    {
        $needle = mb_strtolower(trim($this->boardSearch));

        return $this->project->tasks->filter(function (Task $t) use ($needle) {
            if ($needle === '') {
                return true;
            }

            if (mb_stripos($t->title, $needle) !== false) {
                return true;
            }

            $body = (string) ($t->body ?? '');

            return $body !== '' && mb_stripos($body, $needle) !== false;
        });
    }

    /**
     * @return list<int>
     */
    #[Computed]
    public function assigneeUserIds(): array
    {
        $ids = $this->project->members->pluck('id')->push($this->project->user_id)->unique()->values()->all();

        return array_map(intval(...), $ids);
    }

    #[Computed]
    public function roadmapPlanTasksFiltered()
    {
        $tasks = $this->project->tasks->loadMissing(['okrObjective.goal', 'linksAsTarget.source']);
        $tagNeedle = mb_strtolower(trim($this->roadmapFilterTag));

        $filtered = $tasks->filter(function (Task $t) use ($tagNeedle) {
            if ($tagNeedle === '') {
                return true;
            }
            $tags = is_array($t->tags) ? $t->tags : [];

            foreach ($tags as $tag) {
                if (mb_stripos(mb_strtolower((string) $tag), $tagNeedle) !== false) {
                    return true;
                }
            }

            return false;
        });

        if ($this->roadmapFilterPlanning !== '') {
            $filtered = $filtered->filter(
                fn (Task $t) => (string) ($t->planning_status ?? '') === $this->roadmapFilterPlanning
            );
        }

        $filtered = $filtered->values();

        return match ($this->roadmapSort) {
            'timeline' => $filtered->sortBy(function (Task $t) {
                $s = $t->initiativeStart();

                return $s ? $s->timestamp : PHP_INT_MAX;
            })->values(),
            'status' => $filtered->sortBy(function (Task $t) {
                return [(string) ($t->planning_status ?? 'zzz'), mb_strtolower($t->title)];
            })->values(),
            'okr' => $filtered->sortBy(function (Task $t) {
                $g = $t->okrObjective?->goal?->title ?? 'zzz';
                $o = $t->okrObjective?->title ?? 'zzz';

                return [mb_strtolower($g), mb_strtolower($o), mb_strtolower($t->title)];
            })->values(),
            default => $filtered->sortBy(fn (Task $t) => mb_strtolower($t->title))->values(),
        };
    }

    #[Computed]
    public function roadmapPlanTasksTotal(): int
    {
        return (int) $this->roadmapPlanTasksFiltered->count();
    }

    #[Computed]
    public function roadmapPlanTasksLastPage(): int
    {
        $per = max(5, min(100, (int) $this->roadmapPlanPerPage));
        $total = $this->roadmapPlanTasksTotal;

        return max(1, (int) ceil($total / $per));
    }

    #[Computed]
    public function roadmapPlanTasks()
    {
        $all = $this->roadmapPlanTasksFiltered;
        $page = max(1, $this->roadmapPlanPage);
        $per = max(5, min(100, (int) $this->roadmapPlanPerPage));

        return $all->forPage($page, $per)->values();
    }

    #[Computed]
    public function initiativeTimelineTasks()
    {
        return $this->project->tasks->filter(function (Task $t) {
            return $t->initiativeStart() !== null && $t->initiativeEnd() !== null;
        })->values();
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
                'theme',
                'assignee',
                'okrObjective.goal',
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
        $this->editProjectUrl = (string) ($this->project->url ?? '');
        $this->editingProject = true;
    }

    public function cancelEditProject(): void
    {
        $this->editingProject = false;
        $this->reset('editProjectName', 'editProjectDescription', 'editProjectUrl');
    }

    public function saveProject(): void
    {
        $this->authorize('update', $this->project);

        $validated = $this->validate([
            'editProjectName' => ['required', 'string', 'max:120'],
            'editProjectDescription' => ['nullable', 'string', 'max:2000'],
            'editProjectUrl' => ['nullable', 'url', 'max:2048'],
        ]);

        $this->project->update([
            'name' => $validated['editProjectName'],
            'description' => $validated['editProjectDescription'] ?: null,
            'url' => $validated['editProjectUrl'] ?: null,
        ]);

        $this->editingProject = false;
        $this->reset('editProjectName', 'editProjectDescription', 'editProjectUrl');
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
        $task = Task::query()->where('project_id', $this->projectId)->find($taskId);
        if ($task) {
            $this->editTaskPriority = (int) $task->priority;
            $this->editTaskDueDate = $task->due_date?->format('Y-m-d') ?? '';
            $tags = $task->tags;
            $this->editTaskTags = is_array($tags) ? implode(', ', $tags) : '';
            $this->editTaskAssigneeId = $task->assigned_to;
            $this->editTaskThemeId = $task->theme_id;
            $this->editTaskOkrObjectiveId = $task->okr_objective_id !== null ? (string) $task->okr_objective_id : '';
            $this->editTaskStartsAt = $task->starts_at?->format('Y-m-d') ?? '';
            $this->editTaskEndsAt = $task->ends_at?->format('Y-m-d') ?? '';
            $this->editTaskPlanningStatus = (string) ($task->planning_status ?? '');
        }
        unset($this->focusedTask);
    }

    public function closeTaskDetail(): void
    {
        $this->focusedTaskId = null;
        $this->reset('commentBody', 'attachmentFile', 'linkTargetTaskId');
        $this->linkType = 'relates';
        $this->editTaskPriority = 2;
        $this->editTaskDueDate = '';
        $this->editTaskTags = '';
        $this->editTaskAssigneeId = null;
        $this->editTaskThemeId = null;
        $this->editTaskOkrObjectiveId = '';
        $this->editTaskStartsAt = '';
        $this->editTaskEndsAt = '';
        $this->editTaskPlanningStatus = '';
        unset($this->focusedTask);
    }

    public function saveTaskMeta(): void
    {
        $task = $this->focusedTask;
        if (! $task) {
            return;
        }

        $this->authorize('update', $this->project);

        $allowed = $this->assigneeUserIds;

        $objectiveIds = OkrObjective::query()
            ->whereHas('goal', fn ($q) => $q->where('project_id', $this->projectId))
            ->pluck('id')
            ->map(intval(...))
            ->all();

        $validated = $this->validate([
            'editTaskPriority' => ['required', 'integer', Rule::in([Task::PRIORITY_LOW, Task::PRIORITY_NORMAL, Task::PRIORITY_HIGH])],
            'editTaskDueDate' => ['nullable', 'date'],
            'editTaskTags' => ['nullable', 'string', 'max:500'],
            'editTaskAssigneeId' => ['nullable', 'integer', Rule::in($allowed)],
            'editTaskThemeId' => ['nullable', 'integer', Rule::exists('roadmap_themes', 'id')->where('project_id', $this->projectId)],
            'editTaskOkrObjectiveId' => ['nullable', 'string', Rule::in(['', ...array_map(strval(...), $objectiveIds)])],
            'editTaskStartsAt' => ['nullable', 'date'],
            'editTaskEndsAt' => [
                'nullable',
                'date',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if ($this->editTaskStartsAt === '') {
                        return;
                    }
                    if (strtotime((string) $value) < strtotime($this->editTaskStartsAt)) {
                        $fail(__('The end date must be on or after the start date.'));
                    }
                },
            ],
            'editTaskPlanningStatus' => ['nullable', 'string', Rule::in(Task::PLANNING_STATUSES)],
        ]);

        $rawTags = $validated['editTaskTags'] ?? '';
        $tags = array_values(array_filter(array_map(trim(...), explode(',', (string) $rawTags))));

        $task->update([
            'priority' => $validated['editTaskPriority'],
            'due_date' => $validated['editTaskDueDate'] ?: null,
            'tags' => $tags !== [] ? $tags : null,
            'assigned_to' => $validated['editTaskAssigneeId'],
            'theme_id' => $validated['editTaskThemeId'],
            'okr_objective_id' => $validated['editTaskOkrObjectiveId'] !== '' && $validated['editTaskOkrObjectiveId'] !== null
                ? (int) $validated['editTaskOkrObjectiveId']
                : null,
            'starts_at' => $validated['editTaskStartsAt'] ?: null,
            'ends_at' => $validated['editTaskEndsAt'] ?: null,
            'planning_status' => $validated['editTaskPlanningStatus'] ?: null,
        ]);

        unset($this->focusedTask, $this->project, $this->roadmapPlanTasksFiltered, $this->initiativeTimelineTasks);
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

    public function inviteMember(): void
    {
        $this->authorize('manageSettings', $this->project);

        $validated = $this->validate([
            'inviteEmail' => ['required', 'email', 'max:255'],
            'inviteRole' => ['required', Rule::in(['admin', 'editor', 'viewer'])],
        ]);

        $this->project->invitations()->create([
            'invited_by' => auth()->id(),
            'email' => $validated['inviteEmail'],
            'role' => $validated['inviteRole'],
        ]);

        $this->reset('inviteEmail', 'inviteRole');
        $this->inviteRole = 'editor';
        unset($this->project);
    }

    public function revokeInvitation(ProjectInvitation $invitation): void
    {
        $this->authorize('manageSettings', $this->project);
        if ($invitation->project_id !== $this->projectId) {
            abort(403);
        }
        $invitation->delete();
        unset($this->project);
    }

    public function removeMember(int $userId): void
    {
        $this->authorize('manageSettings', $this->project);
        if ($userId === $this->project->user_id) {
            return;
        }
        $this->project->members()->detach($userId);
        unset($this->project);
    }

    public function addWebhook(): void
    {
        $this->authorize('manageSettings', $this->project);

        $validated = $this->validate([
            'webhookUrl' => ['required', 'url', 'max:2048'],
            'webhookEvents' => ['nullable', 'string', 'max:500'],
        ]);

        $events = null;
        $raw = trim($validated['webhookEvents'] ?? '');
        if ($raw !== '') {
            $events = array_values(array_filter(array_map(trim(...), explode(',', $raw))));
        }

        $this->project->webhooks()->create([
            'url' => $validated['webhookUrl'],
            'events' => $events,
            'active' => true,
        ]);

        $this->reset('webhookUrl', 'webhookEvents');
        unset($this->project);
    }

    public function deleteWebhook(ProjectWebhook $webhook): void
    {
        $this->authorize('manageSettings', $this->project);
        if ($webhook->project_id !== $this->projectId) {
            abort(403);
        }
        $webhook->delete();
        unset($this->project);
    }

    public function addShareLink(): void
    {
        $this->authorize('manageSettings', $this->project);

        $validated = $this->validate([
            'shareLinkLabel' => ['nullable', 'string', 'max:120'],
        ]);

        $this->project->shareTokens()->create([
            'name' => $validated['shareLinkLabel'] ?: null,
        ]);

        $this->reset('shareLinkLabel');
        unset($this->project);
    }

    public function revokeShareLink(ProjectShareToken $token): void
    {
        $this->authorize('manageSettings', $this->project);
        if ($token->project_id !== $this->projectId) {
            abort(403);
        }
        $token->delete();
        unset($this->project);
    }

    public function addTheme(): void
    {
        $this->authorize('update', $this->project);

        $validated = $this->validate([
            'themeName' => ['required', 'string', 'max:120'],
            'themeColor' => ['required', 'string', 'max:32'],
        ]);

        $max = (int) $this->project->themes()->max('sort_order');

        $this->project->themes()->create([
            'name' => $validated['themeName'],
            'color' => $validated['themeColor'],
            'sort_order' => $max + 1,
        ]);

        $this->reset('themeName', 'themeColor');
        $this->themeColor = '#0d9488';
        unset($this->project);
    }

    public function deleteTheme(RoadmapTheme $theme): void
    {
        $this->authorize('update', $this->project);
        if ($theme->project_id !== $this->projectId) {
            abort(403);
        }
        $theme->delete();
        unset($this->project);
    }

    public function archiveProject(): void
    {
        $this->authorize('manageSettings', $this->project);
        $this->project->update(['archived_at' => now()]);
        unset($this->project);
    }

    public function unarchiveProject(): void
    {
        $this->authorize('manageSettings', $this->project);
        $this->project->update(['archived_at' => null]);
        unset($this->project);
    }

    public function rotateCursorToken(): void
    {
        $this->authorize('update', $this->project);
        $this->revealedCursorToken = app(ProjectCursorTokenIssuer::class)->issue($this->project, auth()->user());
        WaypostCursorArtifacts::flashCursorSetupToken($this->projectId, $this->revealedCursorToken);
        $this->hideRevealedCursorToken = false;
        unset($this->project);
    }

    public function dismissRevealedCursorToken(): void
    {
        $this->hideRevealedCursorToken = true;
    }

    public function addToCursor(): void
    {
        $this->authorize('update', $this->project);
        if ($this->revealedCursorToken === null) {
            $this->revealedCursorToken = app(ProjectCursorTokenIssuer::class)->issue($this->project, auth()->user());
            WaypostCursorArtifacts::flashCursorSetupToken($this->projectId, $this->revealedCursorToken);
        }
        $this->hideRevealedCursorToken = false;
        $url = WaypostCursorArtifacts::cursorMcpInstallUrl($this->project, $this->revealedCursorToken);
        $this->showCursorMcpEmbeddedNotice = true;
        $this->js('window.location.href = '.json_encode($url));
    }

    public function dismissCursorMcpEmbeddedNotice(): void
    {
        $this->showCursorMcpEmbeddedNotice = false;
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

    private function assertOkrGoalOnProject(OkrGoal $goal): void
    {
        if ($goal->project_id !== $this->projectId) {
            abort(403);
        }
    }

    private function assertOkrObjectiveOnProject(OkrObjective $objective): void
    {
        $objective->loadMissing('goal');
        if ($objective->goal->project_id !== $this->projectId) {
            abort(403);
        }
    }

    private function assertOkrKeyResultOnProject(OkrKeyResult $kr): void
    {
        $kr->loadMissing('objective.goal');
        if ($kr->objective->goal->project_id !== $this->projectId) {
            abort(403);
        }
    }

    public function refreshProjectForPoll(): void
    {
        unset($this->project, $this->roadmapPlanTasksFiltered, $this->initiativeTimelineTasks, $this->focusedTask);
    }

    public function addOkrGoal(): void
    {
        $this->authorize('update', $this->project);

        $validated = $this->validate([
            'okrNewGoalTitle' => ['required', 'string', 'max:255'],
        ]);

        $max = (int) $this->project->okrGoals()->max('sort_order');
        $this->project->okrGoals()->create([
            'title' => $validated['okrNewGoalTitle'],
            'sort_order' => $max + 1,
        ]);

        $this->reset('okrNewGoalTitle');
        unset($this->project, $this->roadmapPlanTasksFiltered);
    }

    public function deleteOkrGoal(OkrGoal $goal): void
    {
        $this->assertOkrGoalOnProject($goal);
        $this->authorize('update', $this->project);
        $goal->delete();
        unset($this->project, $this->roadmapPlanTasksFiltered, $this->initiativeTimelineTasks);
    }

    public function addOkrObjective(int $goalId): void
    {
        $this->authorize('update', $this->project);

        $goal = OkrGoal::query()
            ->where('project_id', $this->projectId)
            ->whereKey($goalId)
            ->firstOrFail();

        $validated = $this->validate([
            'okrNewObjectiveTitle' => ['required', 'string', 'max:255'],
        ]);

        $max = (int) $goal->objectives()->max('sort_order');
        $goal->objectives()->create([
            'title' => $validated['okrNewObjectiveTitle'],
            'sort_order' => $max + 1,
        ]);

        $this->reset('okrNewObjectiveTitle');
        unset($this->project, $this->roadmapPlanTasksFiltered);
    }

    public function deleteOkrObjective(OkrObjective $objective): void
    {
        $this->assertOkrObjectiveOnProject($objective);
        $this->authorize('update', $this->project);
        $objective->delete();
        unset($this->project, $this->roadmapPlanTasksFiltered, $this->initiativeTimelineTasks);
    }

    public function addOkrKeyResult(int $objectiveId): void
    {
        $this->authorize('update', $this->project);

        $objective = OkrObjective::query()
            ->whereKey($objectiveId)
            ->whereHas('goal', fn ($q) => $q->where('project_id', $this->projectId))
            ->firstOrFail();

        $validated = $this->validate([
            'okrNewKrTitle' => ['required', 'string', 'max:255'],
            'okrNewKrProgress' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $max = (int) $objective->keyResults()->max('sort_order');
        $objective->keyResults()->create([
            'title' => $validated['okrNewKrTitle'],
            'progress' => $validated['okrNewKrProgress'],
            'sort_order' => $max + 1,
        ]);

        $this->reset('okrNewKrTitle');
        $this->okrNewKrProgress = 0;
        unset($this->project, $this->roadmapPlanTasksFiltered);
    }

    public function deleteOkrKeyResult(OkrKeyResult $keyResult): void
    {
        $this->assertOkrKeyResultOnProject($keyResult);
        $this->authorize('update', $this->project);
        $keyResult->delete();
        unset($this->project, $this->roadmapPlanTasksFiltered);
    }

    public function updateOkrKeyResultProgress(int $keyResultId, mixed $progress): void
    {
        $keyResult = OkrKeyResult::query()->find($keyResultId);
        if (! $keyResult) {
            return;
        }

        $this->assertOkrKeyResultOnProject($keyResult);
        $this->authorize('update', $this->project);

        $p = (int) $progress;
        $p = max(0, min(100, $p));
        $keyResult->update(['progress' => $p]);
        unset($this->project, $this->roadmapPlanTasksFiltered);
    }
}; ?>

@php
    $kanbanLabels = [
        'backlog' => 'Backlog',
        'todo' => 'Ready',
        'in_progress' => 'In progress',
        'in_review' => 'In review',
        'done' => 'Done',
    ];
    $kanbanHints = [
        'backlog' => 'Ideas & parking lot',
        'todo' => 'Ready to pick up',
        'in_progress' => 'Active work',
        'in_review' => 'Ready for a final look',
        'done' => 'Shipped in the board',
    ];
    $planningLabels = [
        \App\Models\Task::PLANNING_ON_TIME => 'On time',
        \App\Models\Task::PLANNING_IN_PROGRESS => 'In progress',
        \App\Models\Task::PLANNING_NOT_STARTED => 'Not started',
        \App\Models\Task::PLANNING_BEHIND => 'Behind',
        \App\Models\Task::PLANNING_BLOCKED => 'Blocked',
    ];
@endphp

<div
    data-echo-project="{{ $projectId }}"
    wire:poll.90s="refreshProjectForPoll"
    class="py-10 px-4 sm:px-6 lg:px-8"
    x-data
    x-on:keydown.window.prevent="
        if ($event.key === '/' && ! ['INPUT','TEXTAREA','SELECT'].includes($event.target.tagName)) {
            $refs.boardSearch?.focus();
        }
    "
>
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
                        <div>
                            <label for="editProjectUrl" class="block text-sm font-medium text-ink">
                                Project URL <span class="text-ink/40 font-normal">(optional)</span>
                            </label>
                            <input
                                wire:model="editProjectUrl"
                                id="editProjectUrl"
                                type="url"
                                inputmode="url"
                                autocomplete="url"
                                class="mt-1 block w-full rounded-lg border-cream-300 shadow-sm focus:border-sage focus:ring-sage"
                                placeholder="https://…"
                            />
                            @error('editProjectUrl')
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
                            @if ($this->project->url)
                                <p class="mt-2 max-w-2xl">
                                    <a
                                        href="{{ $this->project->url }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-sm font-medium text-sage-dark hover:text-sage-deeper break-all"
                                    >
                                        {{ $this->project->url }}
                                    </a>
                                </p>
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

        @if ($this->project->tasks->count() > 300)
            <div
                class="rounded-xl border border-amber-200 bg-amber-50/90 px-4 py-3 text-sm text-amber-950"
                role="status"
            >
                <p class="font-medium">Large board ({{ $this->project->tasks->count() }} tasks)</p>
                <p class="mt-1 text-amber-900/90">
                    Search and roadmap filters help narrow the list. Full lazy loading for the board is not enabled yet, so very large projects may feel slower.
                </p>
            </div>
        @endif

        <div class="border-b border-cream-300">
            <nav class="-mb-px flex gap-1 overflow-x-auto pb-px" aria-label="Project sections">
                @foreach (['board' => 'Board', 'roadmap' => 'Roadmap', 'okrs' => 'OKRs', 'wishlist' => 'Wishlist', 'links' => 'Links', 'sync' => 'Sync', 'settings' => 'Settings'] as $key => $label)
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

        @if ($this->tab === 'sync')
            @can('view', $this->project)
                <div class="rounded-xl border border-sage-light/50 bg-sage-light/10 p-4 sm:p-5" wire:key="tab-sync">
                    <h2 class="text-base font-semibold text-ink">Sync with your editor</h2>

                    <div class="mt-3 flex flex-col gap-2 sm:max-w-xs">
                        <label for="sync-editor" class="text-xs font-semibold uppercase tracking-wide text-ink/60">Editor</label>
                        <select
                            id="sync-editor"
                            wire:model.live="syncEditor"
                            class="rounded-lg border-cream-300 text-sm shadow-sm focus:border-sage focus:ring-sage"
                        >
                            <option value="cursor">Cursor</option>
                            <option value="vscode">Visual Studio Code</option>
                            <option value="vscode_insiders">VS Code Insiders</option>
                            <option value="other">Other assistant</option>
                        </select>
                    </div>

                    @if ($this->syncEditor === 'cursor')
                        <p class="mt-4 text-sm text-ink/80 max-w-2xl leading-relaxed">
                            <span class="font-medium text-ink">Recommended:</span> download the setup ZIP into your repo root, then click <strong>Add to Cursor</strong> — we open Cursor with this project’s API token already in the MCP config (no <code class="rounded bg-cream-200 px-1 py-0.5 text-xs">WAYPOST_API_TOKEN</code> step).
                            The same token appears under <a href="{{ route('profile') }}" wire:navigate class="font-medium text-sage-dark hover:text-sage-deeper underline">Profile → API tokens</a> as <span class="font-medium text-ink">Waypost Cursor: …</span> (revoke there if you need to).
                        </p>
                        @cannot('update', $this->project)
                            <p class="mt-2 text-sm text-ink/65 max-w-2xl">Only project editors can use <strong>Add to Cursor</strong> here. Ask an editor to add the integration, or use <strong>Copy config</strong> if you already have a token.</p>
                        @endcannot
                    @elseif ($this->syncEditor === 'vscode' || $this->syncEditor === 'vscode_insiders')
                        <p class="mt-4 text-sm text-ink/80 max-w-2xl leading-relaxed">
                            Download the ZIP to your repo root, then use <strong>Add to VS Code</strong> or paste the copied config into your <code class="rounded bg-cream-200 px-1 py-0.5 text-xs">mcp.json</code>. Use the token from <code class="rounded bg-cream-200 px-1 py-0.5 text-xs">waypost.json</code> in the ZIP when prompted.
                        </p>
                    @else
                        <p class="mt-4 text-sm text-ink/80 max-w-2xl leading-relaxed">
                            Download the ZIP, then <strong>Copy config</strong> and add it in your assistant’s MCP settings. Use the token from <code class="rounded bg-cream-200 px-1 py-0.5 text-xs">waypost.json</code> in the ZIP.
                        </p>
                    @endif

                    @if ($this->revealedCursorToken && ! $this->hideRevealedCursorToken)
                        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50/90 p-3 max-w-2xl">
                            <p class="text-sm font-medium text-ink">Your token (copy if you need it — the next ZIP also embeds it once)</p>
                            <code class="mt-2 block select-all break-all rounded bg-white p-2 text-xs text-ink ring-1 ring-cream-300">{{ $this->revealedCursorToken }}</code>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    wire:click="dismissRevealedCursorToken"
                                    class="text-sm font-medium text-ink/70 hover:text-ink"
                                >
                                    Hide
                                </button>
                            </div>
                        </div>
                    @endif

                    @if ($this->showCursorMcpEmbeddedNotice)
                        <div class="mt-4 rounded-lg border border-sage-light/60 bg-sage-light/15 p-3 max-w-2xl text-sm text-ink/85">
                            <p>
                                Opening Cursor with this project’s token in the install payload. You’ll see the same token listed under
                                <a href="{{ route('profile') }}" wire:navigate class="font-medium text-sage-dark hover:text-sage-deeper underline">Profile → API tokens</a>
                                (name starts with <span class="font-medium text-ink">Waypost Cursor:</span>).
                            </p>
                            <button
                                type="button"
                                wire:click="dismissCursorMcpEmbeddedNotice"
                                class="mt-2 text-sm font-medium text-ink/70 hover:text-ink"
                            >
                                Dismiss
                            </button>
                        </div>
                    @endif

                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <a
                            href="{{ route('projects.waypost-cursor-setup', $this->project) }}"
                            class="inline-flex items-center rounded-lg bg-sage px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-sage-dark"
                        >
                            Download setup (ZIP)
                        </a>
                        @if ($this->syncEditor === 'cursor')
                            @can('update', $this->project)
                                <button
                                    type="button"
                                    wire:click="addToCursor"
                                    class="inline-flex items-center rounded-lg border border-sage-dark/30 bg-white px-4 py-2.5 text-sm font-semibold text-sage-deeper shadow-sm hover:bg-cream-50"
                                >
                                    Add to Cursor
                                </button>
                            @endcan
                        @elseif ($this->syncMcpInstallHref)
                            <a
                                href="{{ $this->syncMcpInstallHref }}"
                                class="inline-flex items-center rounded-lg border border-sage-dark/30 bg-white px-4 py-2.5 text-sm font-semibold text-sage-deeper shadow-sm hover:bg-cream-50"
                            >
                                Add to {{ $this->syncInstallButtonLabel }}
                            </a>
                        @endif
                        <button
                            type="button"
                            x-data="{ copied: false }"
                            @click="navigator.clipboard.writeText(@js($this->syncMcpCopySnippet)); copied = true; setTimeout(() => copied = false, 2000)"
                            class="inline-flex items-center rounded-lg border border-cream-300 bg-white px-4 py-2.5 text-sm font-medium text-ink shadow-sm hover:bg-cream-50"
                        >
                            <span x-text="copied ? 'Copied!' : 'Copy config'"></span>
                        </button>
                    </div>

                    @can('update', $this->project)
                        <p class="mt-3 text-xs text-ink/55">
                            <button
                                type="button"
                                wire:click="rotateCursorToken"
                                class="font-medium text-sage-dark hover:text-sage-deeper underline"
                            >Rotate API token</button>
                            <span class="text-ink/45"> if it was leaked. The next ZIP can include the new token once.</span>
                        </p>
                    @endcan

                    <details class="mt-6 rounded-lg border border-cream-300/80 bg-white/60 p-3 text-sm text-ink/70">
                        <summary class="cursor-pointer font-medium text-ink select-none">Advanced</summary>
                        <div class="mt-3 space-y-3 max-w-3xl">
                            <p>
                                The ZIP contains <code class="rounded bg-cream-200 px-1 text-xs">waypost.json</code>, a Cursor rule under <code class="rounded bg-cream-200 px-1 text-xs">.cursor/rules/</code>, and a short README.
                                The <strong>first</strong> ZIP download after you create or <strong>rotate</strong> the project token (same browser) can include <code class="rounded bg-cream-200 px-1 text-xs">api_token</code>; later downloads omit it. Never commit secrets.
                            </p>
                            <p>
                                MCP is served at <code class="rounded bg-cream-200 px-1 text-xs select-all break-all">{{ \App\Support\WaypostCursorArtifacts::mcpHttpUrl() }}</code>. <strong>Add to Cursor</strong> embeds your project token in the install link; <strong>Copy config</strong> still uses <code class="rounded bg-cream-200 px-1 text-xs">WAYPOST_API_TOKEN</code> in the Authorization header for manual setup.
                            </p>
                            <p class="text-sm text-ink/70">
                                <strong>Cursor MCP debugging:</strong> Cursor uses Chromium networking: HTTP to <code class="rounded bg-cream-200 px-1 text-xs">*.test</code> / localhost may require a <strong>Private Network Access</strong> CORS header; Waypost adds <code class="rounded bg-cream-200 px-1 text-xs">Access-Control-Allow-Private-Network</code> on <code class="rounded bg-cream-200 px-1 text-xs">mcp/*</code> (disable with <code class="rounded bg-cream-200 px-1 text-xs">WAYPOST_MCP_ALLOW_PRIVATE_NETWORK_ACCESS_HEADER=false</code> if needed). In <strong>Settings → Features → MCP</strong>, ensure <strong>waypost</strong> is <strong>enabled</strong> — logs like <code class="rounded bg-cream-200 px-1 text-xs">server_disabled</code> mean Cursor never opened a connection. Open the Output panel (<kbd class="rounded bg-cream-200 px-1 text-xs">⌘⇧U</kbd> / <kbd class="rounded bg-cream-200 px-1 text-xs">Ctrl+Shift+U</kbd>) and choose <strong>MCP Logs</strong> from the channel dropdown. Probe TLS from the same Mac: <code class="rounded bg-cream-200 px-1 text-xs select-all break-all">{{ \App\Support\WaypostCursorArtifacts::mcpReachabilityUrl() }}</code> (should return JSON in the browser). If that works but Cursor POST still fails, trust your local HTTPS CA for Electron/Node or point MCP at <code class="rounded bg-cream-200 px-1 text-xs">http://…</code> for Herd/Valet. Streamable HTTP is <strong>POST</strong> only; <strong>SSE GET</strong> fallback <strong>405</strong> is expected. With <code class="rounded bg-cream-200 px-1 text-xs">APP_ENV=local</code>, Waypost logs <code class="rounded bg-cream-200 px-1 text-xs">waypost.mcp.http</code> to <code class="rounded bg-cream-200 px-1 text-xs">storage/logs/laravel.log</code> when POSTs reach PHP (header <code class="rounded bg-cream-200 px-1 text-xs">X-Waypost-Mcp-Request-Id</code>).
                            </p>
                            @if ($this->syncEditor === 'vscode' || $this->syncEditor === 'vscode_insiders')
                                <p>
                                    VS Code MCP install uses <code class="rounded bg-cream-200 px-1 text-xs">vscode:mcp/install</code>
                                    @if ($this->syncEditor === 'vscode_insiders')
                                        or <code class="rounded bg-cream-200 px-1 text-xs">vscode-insiders:mcp/install</code>
                                    @endif
                                    . The bundled rule file is Cursor MDC format — adapt for VS Code if needed.
                                </p>
                            @endif
                            <p class="flex flex-wrap gap-x-4 gap-y-1">
                                <a
                                    href="{{ route('projects.cursor-rule.agent-activity', $this->project) }}"
                                    download
                                    class="font-medium text-sage-dark hover:text-sage-deeper underline"
                                >Rule file only</a>
                                <a
                                    href="{{ route('projects.waypost-manifest', $this->project) }}"
                                    download
                                    class="font-medium text-sage-dark hover:text-sage-deeper underline"
                                >waypost.json only</a>
                                <a href="{{ route('docs.api') }}" wire:navigate class="font-medium text-sage-dark hover:text-sage-deeper underline">API docs</a>
                            </p>
                            <p class="text-xs text-ink/55 font-mono break-all">
                                Optional env for clients that still call the JSON API directly: <span class="select-all">WAYPOST_BASE_URL={{ \App\Support\WaypostCursorArtifacts::publicBaseUrl() }}</span>
                            </p>
                        </div>
                    </details>
                </div>
            @endcan
        @endif

        {{-- Kanban --}}
        @if ($this->tab === 'board')
            <div class="space-y-6" wire:key="tab-board">
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <p class="text-xs text-ink/50">Press <kbd class="rounded border border-cream-300 bg-cream-100 px-1">/</kbd> to focus search.</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            x-ref="boardSearch"
                            type="search"
                            wire:model.live.debounce.300ms="boardSearch"
                            placeholder="Search cards…"
                            class="min-w-[12rem] flex-1 rounded-lg border-cream-300 text-sm shadow-sm focus:border-sage focus:ring-sage sm:max-w-xs"
                        />
                        <div class="inline-flex rounded-lg border border-cream-300 p-0.5 text-xs font-semibold">
                            <button
                                type="button"
                                wire:click="$set('boardLayout', 'columns')"
                                class="rounded-md px-2 py-1 {{ $this->boardLayout === 'columns' ? 'bg-sage text-white' : 'text-ink/70' }}"
                            >
                                Columns
                            </button>
                            <button
                                type="button"
                                wire:click="$set('boardLayout', 'list')"
                                class="rounded-md px-2 py-1 {{ $this->boardLayout === 'list' ? 'bg-sage text-white' : 'text-ink/70' }}"
                            >
                                List
                            </button>
                        </div>
                    </div>
                </div>

                @if ($this->boardLayout === 'list')
                    <section class="rounded-2xl border border-cream-300/80 bg-white p-6 shadow-sm ring-1 ring-ink/5">
                        <h2 class="text-lg font-semibold text-ink">All cards (list)</h2>
                        <ul class="mt-4 divide-y divide-cream-200">
                            @php $statusOrder = array_flip(Task::KANBAN_STATUSES); @endphp
                            @foreach ($this->boardTasks->sortBy(fn (Task $t) => [$statusOrder[$t->status] ?? 99, $t->position]) as $task)
                                <li wire:key="list-task-{{ $task->id }}" class="flex flex-wrap items-center justify-between gap-2 py-3">
                                    <div class="min-w-0">
                                        <span class="text-sm font-medium text-ink">{{ $task->title }}</span>
                                        <span class="ms-2 text-xs text-ink/55">{{ $kanbanLabels[$task->status] ?? $task->status }}</span>
                                        @if ($task->blocking_links_count > 0)
                                            <span class="ms-2 text-xs font-semibold text-amber-800">Blocked</span>
                                        @endif
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="openTaskDetail({{ $task->id }})"
                                        class="text-sm font-medium text-sage-dark hover:text-sage-deeper"
                                    >
                                        Open
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <div
                    data-kanban-board
                    data-kanban-init="{{ $this->boardLayout === 'columns' && trim($this->boardSearch) === '' ? '1' : '0' }}"
                    class="overflow-x-auto pb-2 -mx-1 px-1 {{ $this->boardLayout === 'list' ? 'hidden' : '' }}"
                >
                    <div class="flex min-h-[28rem] gap-4" style="min-width: min(100%, 70rem);">
                        @foreach (Task::KANBAN_STATUSES as $status)
                            @php
                                $colTasks = $this->boardTasks
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
                                                    @if ($task->blocking_links_count > 0)
                                                        <span class="font-semibold text-amber-800">Blocked</span>
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

                @can('update', $this->project)
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
                @endcan

                <section class="rounded-2xl border border-cream-300/80 bg-white p-6 shadow-sm ring-1 ring-ink/5">
                    <h2 class="text-lg font-semibold text-ink">All tasks</h2>
                    <p class="mt-1 text-sm text-ink/55">Remove a card from the project (same as deleting it from the board).</p>
                    <ul class="mt-4 divide-y divide-cream-200">
                        @php $rowStatusOrder = array_flip(Task::KANBAN_STATUSES); @endphp
                        @foreach ($this->project->tasks->sortBy(fn (Task $t) => [$rowStatusOrder[$t->status] ?? 99, $t->position]) as $task)
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
                                    @can('update', $this->project)
                                    <button
                                        type="button"
                                        wire:click="deleteTask({{ $task->id }})"
                                        wire:confirm="Delete this task?"
                                        class="text-sm text-red-600 hover:text-red-700"
                                    >
                                        Delete
                                    </button>
                                    @endcan
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
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between rounded-xl border border-cream-300/80 bg-white p-4 shadow-sm">
                    <a
                        href="{{ route('projects.export.tasks', $this->project) }}"
                        class="text-sm font-semibold text-sage-dark hover:text-sage-deeper underline"
                    >
                        Export all tasks (CSV)
                    </a>
                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-ink/80">
                        <input type="checkbox" wire:model.live="hideShippedVersions" class="rounded border-cream-300 text-sage focus:ring-sage" />
                        Hide shipped versions
                    </label>
                </div>

                <section class="rounded-2xl border border-cream-300/80 bg-white p-6 shadow-sm ring-1 ring-ink/5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-ink">Initiative roadmap</h2>
                            <p class="mt-1 text-sm text-ink/55">List every card with OKR, planning status, tags, timeline, and dependencies. Set fields on a card’s detail panel. With Reverb running, updates from teammates appear without waiting for a full page refresh.</p>
                        </div>
                        <div class="inline-flex rounded-lg border border-cream-300 p-0.5 text-xs font-semibold">
                            <button
                                type="button"
                                wire:click="$set('roadmapPlanView', 'list')"
                                class="rounded-md px-2 py-1 {{ $this->roadmapPlanView === 'list' ? 'bg-sage text-white' : 'text-ink/70' }}"
                            >
                                List
                            </button>
                            <button
                                type="button"
                                wire:click="$set('roadmapPlanView', 'timeline')"
                                class="rounded-md px-2 py-1 {{ $this->roadmapPlanView === 'timeline' ? 'bg-sage text-white' : 'text-ink/70' }}"
                            >
                                Timeline
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end">
                        <div class="min-w-[10rem] flex-1">
                            <label class="block text-xs font-medium text-ink/70">Filter by tag</label>
                            <input
                                type="search"
                                wire:model.live.debounce.300ms="roadmapFilterTag"
                                placeholder="e.g. API, mobile…"
                                class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm"
                            />
                        </div>
                        <div class="w-full sm:w-44">
                            <label class="block text-xs font-medium text-ink/70">Planning status</label>
                            <select wire:model.live="roadmapFilterPlanning" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm">
                                <option value="">All</option>
                                @foreach (Task::PLANNING_STATUSES as $ps)
                                    <option value="{{ $ps }}">{{ $planningLabels[$ps] ?? $ps }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-full sm:w-44">
                            <label class="block text-xs font-medium text-ink/70">Sort</label>
                            <select wire:model.live="roadmapSort" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm">
                                <option value="title">Title</option>
                                <option value="okr">OKR</option>
                                <option value="status">Planning status</option>
                                <option value="timeline">Timeline</option>
                            </select>
                        </div>
                        <div class="w-full sm:w-28">
                            <label class="block text-xs font-medium text-ink/70">Per page</label>
                            <select wire:model.live="roadmapPlanPerPage" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                    </div>

                    @if ($this->project->tasks->isEmpty())
                        <div class="mt-6 rounded-xl border border-dashed border-cream-300 bg-cream-50/90 p-6 text-sm text-ink/70">
                            <p class="font-medium text-ink">No cards yet</p>
                            <p class="mt-1">Create tasks on the <strong>Board</strong> tab first. Then link them to OKRs and dates from each card’s planning section.</p>
                        </div>
                    @endif

                    @if ($this->roadmapPlanView === 'list')
                        <div class="mt-6 overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-cream-200 text-xs font-semibold uppercase tracking-wide text-ink/55">
                                        <th class="py-2 pe-4">Item</th>
                                        <th class="py-2 pe-4">OKR</th>
                                        <th class="py-2 pe-4">Status</th>
                                        <th class="py-2 pe-4">Tags</th>
                                        <th class="py-2 pe-4">Timeline</th>
                                        <th class="py-2 pe-4">Dependencies</th>
                                        <th class="py-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-cream-100">
                                    @forelse ($this->roadmapPlanTasks as $rt)
                                        <tr wire:key="plan-row-{{ $rt->id }}" @class(['bg-amber-50/40' => $rt->blocking_links_count > 0])>
                                            <td class="py-3 pe-4 font-medium text-ink">{{ $rt->title }}</td>
                                            <td class="py-3 pe-4 text-ink/75">
                                                @if ($rt->okrObjective)
                                                    <span class="text-xs text-ink/50">{{ $rt->okrObjective->goal->title }}</span>
                                                    <span class="block">{{ $rt->okrObjective->title }}</span>
                                                @else
                                                    <span class="text-ink/40">—</span>
                                                @endif
                                            </td>
                                            <td class="py-3 pe-4">
                                                @if ($rt->planning_status)
                                                    <span class="rounded-full bg-cream-100 px-2 py-0.5 text-xs font-medium text-ink">{{ $planningLabels[$rt->planning_status] ?? $rt->planning_status }}</span>
                                                @else
                                                    <span class="text-xs text-ink/40">—</span>
                                                @endif
                                                @if ($rt->blocking_links_count > 0)
                                                    <span class="ms-1 text-xs font-semibold text-amber-800">Blocked</span>
                                                @endif
                                            </td>
                                            <td class="py-3 pe-4 text-xs text-ink/70">
                                                @if (is_array($rt->tags) && count($rt->tags) > 0)
                                                    {{ implode(', ', $rt->tags) }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="py-3 pe-4 text-xs text-ink/70 whitespace-nowrap">
                                                @php
                                                    $rs = $rt->initiativeStart();
                                                    $re = $rt->initiativeEnd();
                                                @endphp
                                                @if ($rs && $re)
                                                    {{ $rs->format('M j') }} – {{ $re->format('M j, Y') }}
                                                @elseif ($rs)
                                                    {{ $rs->format('M j, Y') }}
                                                @elseif ($re)
                                                    {{ $re->format('M j, Y') }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="py-3 pe-4 text-xs text-ink/70">
                                                @if ($rt->linksAsTarget->isNotEmpty())
                                                    <span class="font-semibold text-amber-900">Blocked by</span>
                                                    @foreach ($rt->linksAsTarget as $blk)
                                                        <span class="block">{{ $blk->source->title }}</span>
                                                    @endforeach
                                                @else
                                                    <span class="text-ink/40">—</span>
                                                @endif
                                            </td>
                                            <td class="py-3">
                                                <button
                                                    type="button"
                                                    wire:click="openTaskDetail({{ $rt->id }})"
                                                    class="text-sm font-semibold text-sage-dark hover:text-sage-deeper"
                                                >
                                                    Open
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="py-8 text-center text-sm text-ink/55">
                                                @if ($this->project->tasks->isEmpty())
                                                    No tasks yet. Add cards on the Board tab.
                                                @else
                                                    No tasks match these filters. Clear the tag or status filter, or try another sort.
                                                @endif
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if ($this->roadmapPlanTasksLastPage > 1)
                            <div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm text-ink/70">
                                <span>Showing {{ $this->roadmapPlanTasks->count() }} of {{ $this->roadmapPlanTasksTotal }} (page {{ $this->roadmapPlanPage }} / {{ $this->roadmapPlanTasksLastPage }})</span>
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        wire:click="gotoRoadmapPlanPage({{ $this->roadmapPlanPage - 1 }})"
                                        @disabled($this->roadmapPlanPage <= 1)
                                        class="rounded-lg border border-cream-300 px-3 py-1.5 font-semibold text-ink hover:bg-cream-100 disabled:opacity-40"
                                    >
                                        Previous
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="gotoRoadmapPlanPage({{ $this->roadmapPlanPage + 1 }})"
                                        @disabled($this->roadmapPlanPage >= $this->roadmapPlanTasksLastPage)
                                        class="rounded-lg border border-cream-300 px-3 py-1.5 font-semibold text-ink hover:bg-cream-100 disabled:opacity-40"
                                    >
                                        Next
                                    </button>
                                </div>
                            </div>
                        @endif
                    @else
                        @php
                            $tlTasks = $this->initiativeTimelineTasks;
                            $rangeStart = null;
                            $rangeEnd = null;
                            foreach ($tlTasks as $t) {
                                $s = $t->initiativeStart();
                                $e = $t->initiativeEnd();
                                if ($s && ($rangeStart === null || $s->lt($rangeStart))) {
                                    $rangeStart = $s->copy()->startOfDay();
                                }
                                if ($e && ($rangeEnd === null || $e->gt($rangeEnd))) {
                                    $rangeEnd = $e->copy()->endOfDay();
                                }
                            }
                            if ($rangeStart && $rangeEnd) {
                                $timelineStart = $rangeStart->copy()->startOfMonth();
                                $timelineEnd = $rangeEnd->copy()->endOfMonth();
                                $totalDays = max(1, $timelineStart->diffInDays($timelineEnd) + 1);
                                $monthCursor = $timelineStart->copy();
                                $monthLabels = [];
                                while ($monthCursor->lte($timelineEnd)) {
                                    $monthLabels[] = $monthCursor->copy();
                                    $monthCursor->addMonth()->startOfMonth();
                                }
                            } else {
                                $timelineStart = null;
                                $totalDays = 1;
                                $monthLabels = [];
                            }
                        @endphp
                        @if ($timelineStart)
                            <div class="mt-6 space-y-2">
                                <div class="flex ps-[11rem] pe-1">
                                    @foreach ($monthLabels as $m)
                                        <div class="flex-1 min-w-0 border-l border-cream-200 px-1 text-center text-[10px] font-semibold uppercase tracking-wide text-ink/50">
                                            {{ $m->format('M') }}
                                        </div>
                                    @endforeach
                                </div>
                                @foreach ($tlTasks as $tt)
                                    @php
                                        $ts = $tt->initiativeStart()->startOfDay();
                                        $te = $tt->initiativeEnd()->endOfDay();
                                        $leftPct = min(100, max(0, ($timelineStart->diffInDays($ts) / $totalDays) * 100));
                                        $spanDays = max(1, $ts->diffInDays($te) + 1);
                                        $widthPct = min(100 - $leftPct, max(0.8, ($spanDays / $totalDays) * 100));
                                    @endphp
                                    @php $ttBlocked = $tt->blocking_links_count > 0; @endphp
                                    <div class="flex items-center gap-3 text-sm">
                                        <span class="w-44 shrink-0 truncate text-xs font-medium text-ink" title="{{ $tt->title }}">{{ $tt->title }}</span>
                                        <div class="relative h-7 min-w-0 flex-1 rounded-md {{ $ttBlocked ? 'bg-amber-50 ring-1 ring-amber-200' : 'bg-cream-100' }}">
                                            <div
                                                class="absolute top-1 bottom-1 rounded-md {{ $ttBlocked ? 'bg-amber-600' : 'bg-sage' }} shadow-sm"
                                                style="left: {{ $leftPct }}%; width: {{ $widthPct }}%; min-width: 4px;"
                                            ></div>
                                        </div>
                                        @if ($ttBlocked)
                                            <span class="shrink-0 text-[10px] font-semibold uppercase text-amber-900">Blocked</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            @if ($tlTasks->contains(fn ($t) => $t->blocking_links_count > 0))
                                <p class="mt-4 text-xs text-ink/55">Amber bars indicate tasks with a <strong>blocked by</strong> dependency. Resolve links on the task detail panel.</p>
                            @endif
                        @else
                            <p class="mt-6 text-sm text-ink/55">
                                @if ($this->project->tasks->isEmpty())
                                    Add tasks on the Board tab, then set initiative start/end dates (or a due date) to populate this timeline.
                                @else
                                    No schedulable dates yet. Add start/end dates or a due date on tasks—those dates drive this view and spot overlaps.
                                @endif
                            </p>
                        @endif
                    @endif
                </section>

                @php
                    $versionsList = $this->hideShippedVersions
                        ? $this->project->versions->whereNull('released_at')
                        : $this->project->versions;
                    $ganttVersions = $versionsList->filter(fn ($v) => $v->target_date)->sortBy('target_date');
                @endphp

                @if ($ganttVersions->isNotEmpty())
                    <section class="rounded-2xl border border-cream-300/80 bg-white p-6 shadow-sm ring-1 ring-ink/5">
                        <h2 class="text-lg font-semibold text-ink">Version target dates</h2>
                        <p class="mt-1 text-sm text-ink/55">Quick view of release targets (separate from initiative timeline above).</p>
                        <ul class="mt-4 space-y-3">
                            @foreach ($ganttVersions as $gv)
                                <li class="flex flex-wrap items-center gap-3 text-sm">
                                    <span class="w-40 shrink-0 truncate font-medium text-ink">{{ $gv->name }}</span>
                                    <div class="min-w-[8rem] flex-1 h-2 overflow-hidden rounded-full bg-cream-200">
                                        <div class="h-full rounded-full bg-sage" style="width: 100%"></div>
                                    </div>
                                    <span class="shrink-0 text-xs text-ink/60">{{ $gv->target_date->format('M j, Y') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

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
                    @forelse ($versionsList as $version)
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
                                    <a
                                        href="{{ route('projects.export.version', [$this->project, $version]) }}"
                                        class="inline-flex items-center rounded-lg border border-cream-300 px-3 py-1.5 text-xs font-semibold text-ink hover:bg-cream-100"
                                    >
                                        Export .md
                                    </a>
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

        @if ($this->tab === 'okrs')
            <div class="space-y-8" wire:key="tab-okrs">
                <section class="rounded-2xl border border-cream-300/80 bg-white p-6 shadow-sm ring-1 ring-ink/5">
                    <h2 class="text-lg font-semibold text-ink">Goals &amp; OKRs</h2>
                    <p class="mt-1 text-sm text-ink/55">Group objectives under strategic goals, add measurable key results, and link cards from the planning panel on each task.</p>
                    <div class="mt-4 rounded-lg border border-cream-200 bg-cream-50/90 p-4 text-sm text-ink/75">
                        <p class="font-medium text-ink">Getting started</p>
                        <ol class="mt-2 list-decimal space-y-1 ps-5">
                            <li>Add a <strong>goal</strong> (a strategic theme).</li>
                            <li>Add <strong>objectives</strong> under it, then <strong>key results</strong> with progress sliders.</li>
                            <li>Open any board card → save <strong>OKR objective</strong> in planning to tie work to outcomes.</li>
                        </ol>
                    </div>

                    @can('update', $this->project)
                        <form wire:submit="addOkrGoal" class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div class="min-w-0 flex-1">
                                <label class="block text-xs font-medium text-ink/70">New goal</label>
                                <input
                                    wire:model="okrNewGoalTitle"
                                    type="text"
                                    class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm"
                                    placeholder="e.g. Accelerate product-led growth"
                                />
                                @error('okrNewGoalTitle')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <button type="submit" class="rounded-lg bg-sage px-4 py-2 text-sm font-semibold text-white shadow hover:bg-sage-dark">
                                Add goal
                            </button>
                        </form>
                    @endcan
                </section>

                @forelse ($this->project->okrGoals as $goal)
                    <section wire:key="okr-goal-{{ $goal->id }}" class="rounded-2xl border border-cream-300/80 bg-white p-6 shadow-sm ring-1 ring-ink/5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-xl font-semibold text-ink">{{ $goal->title }}</h3>
                                <p class="mt-1 text-sm text-ink/55">Overall progress (avg. of objectives): <span class="font-semibold text-sage-dark">{{ $goal->averageProgress() }}%</span></p>
                            </div>
                            @can('update', $this->project)
                                <button
                                    type="button"
                                    wire:click="deleteOkrGoal({{ $goal->id }})"
                                    wire:confirm="Delete this goal and all objectives &amp; key results under it?"
                                    class="text-sm font-semibold text-red-600 hover:text-red-700"
                                >
                                    Delete goal
                                </button>
                            @endcan
                        </div>

                        @can('update', $this->project)
                            <form wire:submit="addOkrObjective({{ $goal->id }})" class="mt-6 flex flex-col gap-2 border-t border-cream-200 pt-6 sm:flex-row sm:items-end">
                                <div class="min-w-0 flex-1">
                                    <label class="block text-xs font-medium text-ink/70">New objective under this goal</label>
                                    <input
                                        wire:model="okrNewObjectiveTitle"
                                        type="text"
                                        class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm"
                                        placeholder="e.g. Improve activation rate"
                                    />
                                    @error('okrNewObjectiveTitle')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <button type="submit" class="shrink-0 rounded-lg bg-ink px-4 py-2 text-sm font-semibold text-cream-50 hover:bg-umber">
                                    Add objective
                                </button>
                            </form>
                        @endcan

                        <ul class="mt-6 space-y-6">
                            @foreach ($goal->objectives as $objective)
                                <li wire:key="okr-obj-{{ $objective->id }}" class="rounded-xl border border-cream-200 bg-cream-50/50 p-4">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <h4 class="font-semibold text-ink">{{ $objective->title }}</h4>
                                            <p class="mt-1 text-xs text-ink/55">Objective progress: <span class="font-semibold text-sage-dark">{{ $objective->averageProgress() }}%</span></p>
                                        </div>
                                        @can('update', $this->project)
                                            <button
                                                type="button"
                                                wire:click="deleteOkrObjective({{ $objective->id }})"
                                                wire:confirm="Delete this objective and its key results? Linked tasks will be unlinked."
                                                class="text-xs font-semibold text-red-600 hover:underline"
                                            >
                                                Remove
                                            </button>
                                        @endcan
                                    </div>

                                    <ul class="mt-4 space-y-3">
                                        @foreach ($objective->keyResults as $kr)
                                            <li wire:key="okr-kr-{{ $kr->id }}" class="flex flex-col gap-2 rounded-lg bg-white p-3 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                                                <span class="min-w-0 flex-1 text-sm text-ink">{{ $kr->title }}</span>
                                                <div class="flex flex-wrap items-center gap-3">
                                                    @can('update', $this->project)
                                                        <label class="flex items-center gap-2 text-xs text-ink/70" x-data="{ krProgress: {{ (int) $kr->progress }} }">
                                                            <span class="whitespace-nowrap"><span x-text="krProgress"></span>%</span>
                                                            <input
                                                                type="range"
                                                                min="0"
                                                                max="100"
                                                                x-model="krProgress"
                                                                x-on:change="$wire.updateOkrKeyResultProgress({{ $kr->id }}, krProgress)"
                                                                class="w-32 accent-sage"
                                                            />
                                                        </label>
                                                        <button
                                                            type="button"
                                                            wire:click="deleteOkrKeyResult({{ $kr->id }})"
                                                            wire:confirm="Remove this key result?"
                                                            class="text-xs text-red-600 hover:underline"
                                                        >
                                                            Delete
                                                        </button>
                                                    @else
                                                        <span class="text-sm font-semibold text-sage-dark">{{ $kr->progress }}%</span>
                                                    @endcan
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>

                                    @can('update', $this->project)
                                        <form wire:submit="addOkrKeyResult({{ $objective->id }})" class="mt-4 flex flex-col gap-2 border-t border-cream-200 pt-4 sm:flex-row sm:items-end">
                                            <div class="min-w-0 flex-1">
                                                <label class="block text-xs text-ink/70">Key result</label>
                                                <input
                                                    wire:model="okrNewKrTitle"
                                                    type="text"
                                                    class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm"
                                                    placeholder="Measurable outcome"
                                                />
                                            </div>
                                            <div class="w-full sm:w-28">
                                                <label class="block text-xs text-ink/70">Progress</label>
                                                <input
                                                    wire:model="okrNewKrProgress"
                                                    type="number"
                                                    min="0"
                                                    max="100"
                                                    class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm"
                                                />
                                            </div>
                                            <button type="submit" class="rounded-lg bg-sage px-3 py-2 text-sm font-semibold text-white hover:bg-sage-dark">
                                                Add KR
                                            </button>
                                        </form>
                                    @endcan
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @empty
                    <p class="rounded-xl border border-dashed border-cream-300 bg-cream-100/80 py-12 text-center text-sm text-ink/70">No goals yet. @can('update', $this->project)Add a goal to start your OKR tree.@else Check back when an editor adds goals.@endcan</p>
                @endforelse
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

        @if ($this->tab === 'settings')
            <div class="max-w-3xl space-y-8" wire:key="tab-settings">
                @if ($this->project->archived_at)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                        This project was archived on {{ $this->project->archived_at->format('M j, Y') }}.
                        @can('manageSettings', $this->project)
                            <button type="button" wire:click="unarchiveProject" class="ms-2 font-semibold text-amber-900 underline">Restore</button>
                        @endcan
                    </div>
                @endif

                <section class="rounded-2xl border border-cream-300/80 bg-white p-6 shadow-sm ring-1 ring-ink/5">
                    <h2 class="text-lg font-semibold text-ink">Recent activity</h2>
                    <p class="mt-1 text-sm text-ink/55">Latest changes to tasks, OKRs, wishlist, and links in this project. API edits are tagged with <code class="rounded bg-cream-200 px-1 text-xs">X-Waypost-Source</code> / <code class="rounded bg-cream-200 px-1 text-xs">x_waypost_source</code> (Cursor, Copilot, Windsurf, etc. — see <code class="rounded bg-cream-200 px-1 text-xs">supported_agent_types</code> in waypost.json).</p>
                    <ul class="mt-4 max-h-80 space-y-2 overflow-y-auto text-sm">
                        @forelse ($this->project->activities as $act)
                            @php
                                $actProps = is_array($act->properties) ? $act->properties : [];
                                $clientSource = $actProps['client_source'] ?? null;
                                $agentTag = $actProps['agent'] ?? null;
                                unset($actProps['client_source'], $actProps['agent']);
                            @endphp
                            <li wire:key="act-{{ $act->id }}" class="rounded-lg border border-cream-200 bg-cream-50/80 px-3 py-2">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <span class="font-mono text-xs font-semibold text-sage-dark">{{ $act->action }}</span>
                                    <span class="text-xs text-ink/50">{{ $act->created_at->diffForHumans() }}</span>
                                </div>
                                @if ($act->user)
                                    <p class="mt-1 text-xs text-ink/60">{{ $act->user->name }}</p>
                                @endif
                                @if (is_string($agentTag) && $agentTag !== '')
                                    <p class="mt-1 flex flex-wrap gap-1">
                                        <span class="inline-block rounded-md bg-umber/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-umber">{{ $agentTag }}</span>
                                        @if (is_string($clientSource) && $clientSource !== '' && $clientSource !== $agentTag)
                                            <span class="inline-block rounded-md bg-sage-light/50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sage-deeper">{{ $clientSource }}</span>
                                        @endif
                                    </p>
                                @elseif (is_string($clientSource) && $clientSource !== '')
                                    <p class="mt-1">
                                        <span class="inline-block rounded-md bg-sage-light/50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sage-deeper">{{ $clientSource }}</span>
                                    </p>
                                @endif
                                @if ($actProps !== [])
                                    <p class="mt-1 text-xs text-ink/70">{{ \Illuminate\Support\Str::limit(json_encode($actProps, JSON_UNESCAPED_UNICODE), 200) }}</p>
                                @endif
                            </li>
                        @empty
                            <li class="text-ink/55">No activity recorded yet. Updates will appear here as you edit tasks, OKRs, wishlist, and links.</li>
                        @endforelse
                    </ul>
                </section>

                @can('manageSettings', $this->project)
                    <section class="rounded-2xl border border-cream-300/80 bg-white p-6 shadow-sm ring-1 ring-ink/5">
                        <h2 class="text-lg font-semibold text-ink">People & invites</h2>
                        <p class="mt-1 text-sm text-ink/55">Invite by email. They must sign in with the same address to accept.</p>
                        <form wire:submit="inviteMember" class="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                            <div class="min-w-0 flex-1">
                                <label class="block text-xs font-medium text-ink/70">Email</label>
                                <input wire:model="inviteEmail" type="email" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm" required />
                                @error('inviteEmail')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-ink/70">Role</label>
                                <select wire:model="inviteRole" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm sm:w-36">
                                    <option value="admin">Admin</option>
                                    <option value="editor">Editor</option>
                                    <option value="viewer">Viewer</option>
                                </select>
                            </div>
                            <button type="submit" class="rounded-lg bg-sage px-4 py-2 text-sm font-semibold text-white shadow hover:bg-sage-dark">Create invite</button>
                        </form>
                        <ul class="mt-6 space-y-3 text-sm">
                            @foreach ($this->project->invitations as $inv)
                                <li wire:key="inv-{{ $inv->id }}" class="flex flex-col gap-2 rounded-lg border border-cream-200 bg-cream-50/80 p-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="font-medium text-ink">{{ $inv->email }}</p>
                                        <p class="text-xs text-ink/55">{{ $inv->role }} · expires {{ $inv->expires_at?->format('M j, Y') }}</p>
                                        <p class="mt-1 break-all text-xs font-mono text-ink/60">{{ route('invitations.accept', ['token' => $inv->token]) }}</p>
                                    </div>
                                    <button type="button" wire:click="revokeInvitation({{ $inv->id }})" class="text-xs font-semibold text-red-700 hover:underline">Revoke</button>
                                </li>
                            @endforeach
                            @if ($this->project->invitations->isEmpty())
                                <li class="text-ink/55">No pending invitations.</li>
                            @endif
                        </ul>
                        <h3 class="mt-8 text-sm font-semibold text-ink">Members</h3>
                        <ul class="mt-2 space-y-2 text-sm">
                            <li class="flex items-center justify-between rounded-lg border border-cream-200 px-3 py-2">
                                <span>{{ $this->project->user->name }} (owner)</span>
                            </li>
                            @foreach ($this->project->members as $m)
                                <li wire:key="mem-{{ $m->id }}" class="flex items-center justify-between rounded-lg border border-cream-200 px-3 py-2">
                                    <span>{{ $m->name }} <span class="text-ink/50">({{ $m->pivot->role }})</span></span>
                                    <button type="button" wire:click="removeMember({{ $m->id }})" class="text-xs text-red-700 hover:underline">Remove</button>
                                </li>
                            @endforeach
                        </ul>
                    </section>

                    <section class="rounded-2xl border border-cream-300/80 bg-white p-6 shadow-sm ring-1 ring-ink/5">
                        <h2 class="text-lg font-semibold text-ink">Public roadmap link</h2>
                        <p class="mt-1 text-sm text-ink/55">Anyone with the link can view versions, OKR progress, initiative timelines, and task titles (read-only).</p>
                        <form wire:submit="addShareLink" class="mt-4 flex flex-wrap items-end gap-3">
                            <div class="min-w-0 flex-1">
                                <label class="block text-xs font-medium text-ink/70">Label <span class="font-normal text-ink/40">(optional)</span></label>
                                <input wire:model="shareLinkLabel" type="text" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm" placeholder="e.g. Stakeholders" />
                            </div>
                            <button type="submit" class="rounded-lg bg-sage px-4 py-2 text-sm font-semibold text-white shadow hover:bg-sage-dark">Generate link</button>
                        </form>
                        <ul class="mt-4 space-y-2 text-sm">
                            @foreach ($this->project->shareTokens as $st)
                                <li wire:key="share-{{ $st->id }}" class="rounded-lg border border-cream-200 bg-cream-50/80 p-3">
                                    <p class="font-medium text-ink">{{ $st->name ?: 'Shared roadmap' }}</p>
                                    <p class="mt-1 break-all text-xs font-mono text-ink/70">{{ route('roadmap.public', ['token' => $st->token]) }}</p>
                                    <button type="button" wire:click="revokeShareLink({{ $st->id }})" class="mt-2 text-xs text-red-700 hover:underline">Revoke</button>
                                </li>
                            @endforeach
                            @if ($this->project->shareTokens->isEmpty())
                                <li class="text-ink/55">No share links yet.</li>
                            @endif
                        </ul>
                    </section>

                    <section class="rounded-2xl border border-cream-300/80 bg-white p-6 shadow-sm ring-1 ring-ink/5">
                        <h2 class="text-lg font-semibold text-ink">Webhooks</h2>
                        <p class="mt-1 text-sm text-ink/55">POST JSON to your URL on task changes. Leave events blank to receive all. Comma-separated event names otherwise (e.g. task.created,task.updated).</p>
                        <form wire:submit="addWebhook" class="mt-4 space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-ink/70">Endpoint URL</label>
                                <input wire:model="webhookUrl" type="url" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm" required />
                                @error('webhookUrl')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-ink/70">Events</label>
                                <input wire:model="webhookEvents" type="text" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm" placeholder="task.created, task.updated" />
                            </div>
                            <button type="submit" class="rounded-lg bg-sage px-4 py-2 text-sm font-semibold text-white shadow hover:bg-sage-dark">Add webhook</button>
                        </form>
                        <ul class="mt-4 space-y-2 text-sm">
                            @foreach ($this->project->webhooks as $wh)
                                <li wire:key="wh-{{ $wh->id }}" class="flex items-start justify-between gap-2 rounded-lg border border-cream-200 px-3 py-2">
                                    <div class="min-w-0">
                                        <p class="truncate font-mono text-xs text-ink">{{ $wh->url }}</p>
                                        <p class="text-xs text-ink/50">{{ $wh->active ? 'Active' : 'Off' }} · secret ends …{{ substr($wh->secret, -4) }}</p>
                                    </div>
                                    <button type="button" wire:click="deleteWebhook({{ $wh->id }})" class="shrink-0 text-xs text-red-700 hover:underline">Delete</button>
                                </li>
                            @endforeach
                            @if ($this->project->webhooks->isEmpty())
                                <li class="text-ink/55">No webhooks configured.</li>
                            @endif
                        </ul>
                    </section>

                    <section class="rounded-2xl border border-cream-300/80 bg-white p-6 shadow-sm ring-1 ring-ink/5">
                        <h2 class="text-lg font-semibold text-ink">Archive project</h2>
                        <p class="mt-1 text-sm text-ink/55">Archived projects stay available but are hidden from the default project list.</p>
                        @if (! $this->project->archived_at)
                            <button
                                type="button"
                                wire:click="archiveProject"
                                wire:confirm="Archive this project?"
                                class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-950 hover:bg-amber-100"
                            >
                                Archive
                            </button>
                        @endif
                    </section>
                @endcan

                @can('update', $this->project)
                    <section class="rounded-2xl border border-cream-300/80 bg-white p-6 shadow-sm ring-1 ring-ink/5">
                        <h2 class="text-lg font-semibold text-ink">Roadmap themes</h2>
                        <p class="mt-1 text-sm text-ink/55">Group tasks under colored themes on the planning fields.</p>
                        <form wire:submit="addTheme" class="mt-4 flex flex-wrap items-end gap-3">
                            <div>
                                <label class="block text-xs font-medium text-ink/70">Name</label>
                                <input wire:model="themeName" type="text" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm sm:w-48" required />
                                @error('themeName')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-ink/70">Color</label>
                                <input wire:model="themeColor" type="color" class="mt-1 h-9 w-16 cursor-pointer rounded border border-cream-300" />
                            </div>
                            <button type="submit" class="rounded-lg bg-sage px-4 py-2 text-sm font-semibold text-white shadow hover:bg-sage-dark">Add theme</button>
                        </form>
                        <ul class="mt-4 space-y-2 text-sm">
                            @foreach ($this->project->themes as $th)
                                <li wire:key="theme-{{ $th->id }}" class="flex items-center justify-between rounded-lg border border-cream-200 px-3 py-2">
                                    <span class="flex items-center gap-2">
                                        <span class="inline-block h-3 w-3 rounded-full ring-1 ring-ink/10" style="background: {{ $th->color }}"></span>
                                        {{ $th->name }}
                                    </span>
                                    <button type="button" wire:click="deleteTheme({{ $th->id }})" class="text-xs text-red-700 hover:underline">Delete</button>
                                </li>
                            @endforeach
                            @if ($this->project->themes->isEmpty())
                                <li class="text-ink/55">No themes yet.</li>
                            @endif
                        </ul>
                    </section>
                @endcan

                @cannot('manageSettings', $this->project)
                    @cannot('update', $this->project)
                        <p class="text-sm text-ink/55">You have read-only access to this project.</p>
                    @endcannot
                @endcannot
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
                            <div class="markdown-body mt-2 max-w-none text-sm leading-relaxed text-ink [&_a]:font-medium [&_a]:text-sage-dark [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:border-cream-300 [&_blockquote]:ps-3 [&_code]:rounded [&_code]:bg-cream-100 [&_code]:px-1 [&_p]:mb-2 [&_ul]:mb-2 [&_ul]:list-disc [&_ul]:ps-5">
                                {!! str($ft->body)->markdown() !!}
                            </div>
                        </div>
                    @endif

                    @can('update', $this->project)
                        <form wire:submit="saveTaskMeta" class="space-y-3 rounded-xl border border-cream-200 bg-cream-50/80 p-4">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-ink/55">Planning</h3>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="block text-xs text-ink/70">Priority</label>
                                    <select wire:model="editTaskPriority" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm">
                                        <option value="{{ Task::PRIORITY_LOW }}">Low</option>
                                        <option value="{{ Task::PRIORITY_NORMAL }}">Normal</option>
                                        <option value="{{ Task::PRIORITY_HIGH }}">High</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-ink/70">Due date</label>
                                    <input wire:model="editTaskDueDate" type="date" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm" />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs text-ink/70">Tags (comma-separated)</label>
                                    <input wire:model="editTaskTags" type="text" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm" placeholder="frontend, bug, polish" />
                                </div>
                                <div>
                                    <label class="block text-xs text-ink/70">Assignee</label>
                                    <select wire:model="editTaskAssigneeId" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm">
                                        <option value="">— Unassigned —</option>
                                        @foreach (collect([$this->project->user])->merge($this->project->members)->unique('id') as $member)
                                            <option value="{{ $member->id }}">{{ $member->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-ink/70">Theme</label>
                                    <select wire:model="editTaskThemeId" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm">
                                        <option value="">— None —</option>
                                        @foreach ($this->project->themes as $th)
                                            <option value="{{ $th->id }}">{{ $th->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs text-ink/70">OKR objective</label>
                                    <select wire:model="editTaskOkrObjectiveId" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm">
                                        <option value="">— None —</option>
                                        @foreach ($this->project->okrGoals as $og)
                                            @foreach ($og->objectives as $oo)
                                                <option value="{{ $oo->id }}">{{ $og->title }} › {{ $oo->title }}</option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-ink/70">Initiative start</label>
                                    <input wire:model="editTaskStartsAt" type="date" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs text-ink/70">Initiative end</label>
                                    <input wire:model="editTaskEndsAt" type="date" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm" />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs text-ink/70">Planning status</label>
                                    <select wire:model="editTaskPlanningStatus" class="mt-1 block w-full rounded-lg border-cream-300 text-sm shadow-sm">
                                        <option value="">— Not set —</option>
                                        @foreach (Task::PLANNING_STATUSES as $ps)
                                            <option value="{{ $ps }}">{{ $planningLabels[$ps] ?? $ps }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="rounded-lg bg-sage px-3 py-2 text-sm font-semibold text-white hover:bg-sage-dark">Save planning fields</button>
                        </form>
                    @endcan

                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-ink/55">Linked tasks</h3>
                        <ul class="mt-2 space-y-2 text-sm">
                            @foreach ($ft->linksAsSource as $link)
                                @if ($link->type === TaskLink::TYPE_RELATES)
                                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-cream-100 px-3 py-2">
                                        <span><span class="text-ink/55">Related to</span>
                                            <button type="button" wire:click="openTaskDetail({{ $link->target_task_id }})" class="ms-1 font-medium text-sage-dark hover:underline">{{ $link->target->title }}</button>
                                        </span>
                                        <button type="button" wire:click="deleteTaskLink({{ $link->id }})" class="text-xs text-red-600 hover:underline">Remove</button>
                                    </li>
                                @else
                                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-amber-50/80 px-3 py-2">
                                        <span><span class="text-ink/70">Blocks</span>
                                            <button type="button" wire:click="openTaskDetail({{ $link->target_task_id }})" class="ms-1 font-medium text-sage-dark hover:underline">{{ $link->target->title }}</button>
                                        </span>
                                        <button type="button" wire:click="deleteTaskLink({{ $link->id }})" class="text-xs text-red-600 hover:underline">Remove</button>
                                    </li>
                                @endif
                            @endforeach
                            @foreach ($ft->linksAsTarget as $link)
                                @if ($link->type === TaskLink::TYPE_RELATES)
                                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-cream-100 px-3 py-2">
                                        <span><span class="text-ink/55">Related to</span>
                                            <button type="button" wire:click="openTaskDetail({{ $link->source_task_id }})" class="ms-1 font-medium text-sage-dark hover:underline">{{ $link->source->title }}</button>
                                        </span>
                                        <button type="button" wire:click="deleteTaskLink({{ $link->id }})" class="text-xs text-red-600 hover:underline">Remove</button>
                                    </li>
                                @else
                                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-red-50/60 px-3 py-2">
                                        <span><span class="text-ink/70">Blocked by</span>
                                            <button type="button" wire:click="openTaskDetail({{ $link->source_task_id }})" class="ms-1 font-medium text-sage-dark hover:underline">{{ $link->source->title }}</button>
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
