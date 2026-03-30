<?php

namespace App\Observers;

use App\Models\OkrKeyResult;
use App\Services\ProjectActivityRecorder;

class OkrKeyResultObserver
{
    public function __construct(private ProjectActivityRecorder $activity) {}

    public function created(OkrKeyResult $keyResult): void
    {
        $keyResult->loadMissing('objective.goal');
        $pid = $keyResult->objective->goal->project_id;
        $this->activity->record(auth()->user(), $pid, 'okr.key_result.created', 'okr_key_result', $keyResult->id, [
            'title' => $keyResult->title,
            'progress' => $keyResult->progress,
        ]);
    }

    public function updated(OkrKeyResult $keyResult): void
    {
        $keyResult->loadMissing('objective.goal');
        $pid = $keyResult->objective->goal->project_id;
        if ($keyResult->wasChanged(['title', 'progress'])) {
            $this->activity->record(auth()->user(), $pid, 'okr.key_result.updated', 'okr_key_result', $keyResult->id, [
                'title' => $keyResult->title,
                'progress' => $keyResult->progress,
            ]);
        }
    }

    public function deleted(OkrKeyResult $keyResult): void
    {
        $keyResult->loadMissing('objective.goal');
        $pid = $keyResult->objective->goal->project_id;
        $this->activity->record(auth()->user(), $pid, 'okr.key_result.deleted', 'okr_key_result', $keyResult->id, [
            'title' => $keyResult->title,
        ]);
    }
}
