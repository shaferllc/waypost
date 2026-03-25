<?php

namespace App\Observers;

use App\Events\ProjectDataUpdated;
use App\Models\ProjectLink;
use App\Services\ProjectActivityRecorder;

class ProjectLinkObserver
{
    public function __construct(private ProjectActivityRecorder $activity) {}

    public function created(ProjectLink $link): void
    {
        broadcast(new ProjectDataUpdated($link->project_id));
        $this->maybeRecord($link, 'project_link.created', [
            'title' => $link->title,
            'url' => $link->url,
        ]);
    }

    public function deleted(ProjectLink $link): void
    {
        broadcast(new ProjectDataUpdated($link->project_id));
        $this->maybeRecord($link, 'project_link.deleted', [
            'title' => $link->title,
            'url' => $link->url,
        ]);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function maybeRecord(ProjectLink $link, string $action, array $properties): void
    {
        if (! auth()->check()) {
            return;
        }

        $this->activity->record(auth()->user(), $link->project_id, $action, 'project_link', $link->id, $properties);
    }
}
