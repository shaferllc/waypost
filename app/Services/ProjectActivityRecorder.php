<?php

namespace App\Services;

use App\Models\ProjectActivity;
use App\Models\User;
use App\Support\WaypostSource;

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
        $properties = $this->withApiClientSource($properties);

        ProjectActivity::query()->create([
            'project_id' => $projectId,
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'properties' => $properties,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $properties
     * @return array<string, mixed>|null
     */
    private function withApiClientSource(?array $properties): ?array
    {
        if (! request()->is('api/*')) {
            return $properties;
        }

        $properties ??= [];
        $properties['client_source'] = WaypostSource::normalize(request()->header('X-Waypost-Source'));

        return $properties;
    }
}
