<?php

namespace App\Services;

use App\Models\ChangelogEntry;
use App\Models\User;
use App\Support\WaypostSource;

class ChangelogRecorder
{
    public function record(
        User $user,
        string $action,
        string $summary,
        ?int $projectId = null,
        ?array $meta = null,
        ?string $source = null,
    ): void {
        $normalizedSource = WaypostSource::normalize($source ?? 'api');

        ChangelogEntry::query()->create([
            'user_id' => $user->id,
            'project_id' => $projectId,
            'source' => $normalizedSource,
            'action' => $action,
            'summary' => mb_substr($summary, 0, 500),
            'meta' => $meta,
        ]);
    }
}
