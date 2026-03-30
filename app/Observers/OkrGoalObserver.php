<?php

namespace App\Observers;

use App\Models\OkrGoal;
use App\Services\ProjectActivityRecorder;

class OkrGoalObserver
{
    public function __construct(private ProjectActivityRecorder $activity) {}

    public function created(OkrGoal $goal): void
    {
        $this->activity->record(auth()->user(), $goal->project_id, 'okr.goal.created', 'okr_goal', $goal->id, [
            'title' => $goal->title,
        ]);
    }

    public function updated(OkrGoal $goal): void
    {
        if ($goal->wasChanged('title')) {
            $this->activity->record(auth()->user(), $goal->project_id, 'okr.goal.updated', 'okr_goal', $goal->id, [
                'title' => $goal->title,
            ]);
        }
    }

    public function deleted(OkrGoal $goal): void
    {
        $this->activity->record(auth()->user(), $goal->project_id, 'okr.goal.deleted', 'okr_goal', $goal->id, [
            'title' => $goal->title,
        ]);
    }
}
