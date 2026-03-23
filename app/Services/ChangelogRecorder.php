<?php

namespace App\Services;

use App\Models\ChangelogEntry;
use App\Models\User;

class ChangelogRecorder
{
    private const ALLOWED_SOURCES = ['api', 'mcp', 'web', 'cursor', 'extension'];

    public function record(
        User $user,
        string $action,
        string $summary,
        ?int $projectId = null,
        ?array $meta = null,
        ?string $source = null,
    ): void {
        $normalizedSource = strtolower((string) ($source ?? 'api'));
        if (! in_array($normalizedSource, self::ALLOWED_SOURCES, true)) {
            $normalizedSource = 'api';
        }

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
