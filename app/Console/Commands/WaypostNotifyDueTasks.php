<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Notifications\TaskDueSoonNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('waypost:notify-due-tasks')]
#[Description('Notify assignees and project owners about tasks due tomorrow')]
class WaypostNotifyDueTasks extends Command
{
    public function handle(): int
    {
        $date = now()->addDay()->toDateString();

        Task::query()
            ->whereDate('due_date', $date)
            ->with(['project.user', 'assignee'])
            ->chunkById(100, function (Collection $tasks): void {
                foreach ($tasks as $task) {
                    $recipients = collect([$task->assignee, $task->project?->user])
                        ->filter()
                        ->unique('id');

                    foreach ($recipients as $user) {
                        $user->notify(new TaskDueSoonNotification($task));
                    }
                }
            });

        $this->info('Due-task notifications queued for '.$date.'.');

        return self::SUCCESS;
    }
}
