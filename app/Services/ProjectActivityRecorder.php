<?php

namespace App\Services;

use App\Models\ProjectActivity;
use App\Models\User;

class ProjectActivityRecorder
{
    /**
     * @param  array<string, mixed>|null  $properties
     */
    public function record(
        ?User $user,
        int $projectId,
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?array $properties = null,
    ): void {
        ProjectActivity::query()->create([
            'project_id' => $projectId,
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'properties' => $properties,
        ]);
    }
}
