<?php

namespace App\Services;

use App\Models\PersonalAccessToken;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ProjectCursorTokenIssuer
{
    /**
     * Create a new Sanctum token limited to this project. Returns plaintext once.
     */
    public function issue(Project $project, User $user): string
    {
        Gate::forUser($user)->authorize('update', $project);

        $this->revokeForProject($user, $project);

        $label = 'Waypost Cursor: '.Str::limit($project->name, 200);
        $result = $user->createToken($label, ['*']);
        $result->accessToken->forceFill(['project_id' => $project->id])->save();

        return $result->plainTextToken;
    }

    public function hasTokenForProject(User $user, Project $project): bool
    {
        return PersonalAccessToken::query()
            ->where('tokenable_id', $user->id)
            ->where('tokenable_type', $user->getMorphClass())
            ->where('project_id', $project->id)
            ->exists();
    }

    public function revokeForProject(User $user, Project $project): void
    {
        Gate::forUser($user)->authorize('update', $project);

        PersonalAccessToken::query()
            ->where('tokenable_id', $user->id)
            ->where('tokenable_type', $user->getMorphClass())
            ->where('project_id', $project->id)
            ->delete();
    }
}
