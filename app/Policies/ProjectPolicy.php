<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $this->isOwner($user, $project)
            || $project->members()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Project $project): bool
    {
        if ($this->isOwner($user, $project)) {
            return true;
        }

        $role = $project->members()->where('user_id', $user->id)->value('role');

        return in_array($role, ['editor', 'admin'], true);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->isOwner($user, $project);
    }

    public function manageSettings(User $user, Project $project): bool
    {
        if ($this->isOwner($user, $project)) {
            return true;
        }

        $role = $project->members()->where('user_id', $user->id)->value('role');

        return $role === 'admin';
    }

    private function isOwner(User $user, Project $project): bool
    {
        return $user->id === $project->user_id;
    }
}
