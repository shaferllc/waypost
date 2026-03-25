<?php

use App\Models\Project;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('project.{projectId}', function ($user, $projectId) {
    $project = Project::query()->find($projectId);

    if (! $project) {
        return false;
    }

    return $user->can('view', $project);
});
