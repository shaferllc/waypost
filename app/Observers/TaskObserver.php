<?php

namespace App\Observers;

use App\Events\ProjectDataUpdated;
use App\Models\Task;
use App\Services\ChangelogRecorder;
use App\Services\ProjectActivityRecorder;
use App\Services\WebhookDispatcher;

class TaskObserver
{
    public function __construct(private ProjectActivityRecorder $activity) {}

    public function created(Task $task): void
    {
        broadcast(new ProjectDataUpdated($task->project_id));
        $this->recordActivity($task, 'task.created', [
            'title' => $task->title,
            'status' => $task->status,
        ]);

        $this->changelog($task, 'task.created', "Created task: {$task->title}", [
            'task_id' => $task->id,
            'status' => $task->status,
            'version_id' => $task->version_id,
        ]);
        app(WebhookDispatcher::class)->dispatch($task->project, 'task.created', [
            'task' => $this->taskPayload($task),
        ]);
    }

    public function updated(Task $task): void
    {
        $changes = $task->getChanges();
        unset($changes['updated_at']);
        $isKanbanOnly = count($changes) === 2
            && array_key_exists('position', $changes)
            && array_key_exists('status', $changes);

        broadcast(new ProjectDataUpdated($task->project_id));

        if ($isKanbanOnly) {
            $this->recordActivity($task, 'task.moved', [
                'status' => $task->status,
                'position' => $task->position,
            ]);
            app(WebhookDispatcher::class)->dispatch($task->project, 'task.moved', [
                'task_id' => $task->id,
                'status' => $task->status,
                'position' => $task->position,
            ]);

            return;
        }

        if ($changes === []) {
            return;
        }

        $this->recordActivity($task, 'task.updated', [
            'title' => $task->title,
            'changed' => array_keys($changes),
        ]);

        $this->changelog($task, 'task.updated', "Updated task: {$task->title}", [
            'task_id' => $task->id,
            'changes' => array_keys($changes),
        ]);
        app(WebhookDispatcher::class)->dispatch($task->project, 'task.updated', [
            'task' => $this->taskPayload($task),
            'changes' => $changes,
        ]);
    }

    public function deleted(Task $task): void
    {
        broadcast(new ProjectDataUpdated($task->project_id));
        $this->recordActivity($task, 'task.deleted', [
            'title' => $task->title,
        ]);

        $this->changelog($task, 'task.deleted', "Deleted task: {$task->title}", [
            'task_id' => $task->id,
        ]);
        app(WebhookDispatcher::class)->dispatch($task->project, 'task.deleted', [
            'task_id' => $task->id,
            'title' => $task->title,
        ]);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function recordActivity(Task $task, string $action, array $properties): void
    {
        if (! auth()->check()) {
            return;
        }

        $this->activity->record(auth()->user(), $task->project_id, $action, 'task', $task->id, $properties);
    }

    /**
     * @return array<string, mixed>
     */
    private function taskPayload(Task $task): array
    {
        return [
            'id' => $task->id,
            'project_id' => $task->project_id,
            'version_id' => $task->version_id,
            'theme_id' => $task->theme_id,
            'okr_objective_id' => $task->okr_objective_id,
            'assigned_to' => $task->assigned_to,
            'title' => $task->title,
            'body' => $task->body,
            'status' => $task->status,
            'position' => $task->position,
            'priority' => $task->priority,
            'due_date' => $task->due_date?->format('Y-m-d'),
            'starts_at' => $task->starts_at?->format('Y-m-d'),
            'ends_at' => $task->ends_at?->format('Y-m-d'),
            'planning_status' => $task->planning_status,
            'tags' => $task->tags,
        ];
    }

    private function changelog(Task $task, string $action, string $summary, array $meta): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $source = request()->is('api/*')
            ? (request()->header('X-Waypost-Source') ?? 'api')
            : 'web';

        app(ChangelogRecorder::class)->record($user, $action, $summary, $task->project_id, $meta, $source);
    }
}
