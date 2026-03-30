<?php

namespace App\Observers;

use App\Models\OkrObjective;
use App\Services\ProjectActivityRecorder;

class OkrObjectiveObserver
{
    public function __construct(private ProjectActivityRecorder $activity) {}

    public function created(OkrObjective $objective): void
    {
        $objective->loadMissing('goal');
        $pid = $objective->goal->project_id;
        $this->activity->record(auth()->user(), $pid, 'okr.objective.created', 'okr_objective', $objective->id, [
            'title' => $objective->title,
        ]);
    }

    public function updated(OkrObjective $objective): void
    {
        $objective->loadMissing('goal');
        $pid = $objective->goal->project_id;
        if ($objective->wasChanged('title')) {
            $this->activity->record(auth()->user(), $pid, 'okr.objective.updated', 'okr_objective', $objective->id, [
                'title' => $objective->title,
            ]);
        }
    }

    public function deleted(OkrObjective $objective): void
    {
        $objective->loadMissing('goal');
        $pid = $objective->goal->project_id;
        $this->activity->record(auth()->user(), $pid, 'okr.objective.deleted', 'okr_objective', $objective->id, [
            'title' => $objective->title,
        ]);
    }
}
