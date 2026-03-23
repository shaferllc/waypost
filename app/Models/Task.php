<?php

namespace App\Models;

use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id',
    'version_id',
    'theme_id',
    'assigned_to',
    'title',
    'body',
    'status',
    'position',
    'priority',
    'due_date',
    'tags',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    public const KANBAN_STATUSES = ['backlog', 'todo', 'in_progress', 'in_review', 'done'];

    public const PRIORITY_LOW = 1;

    public const PRIORITY_NORMAL = 2;

    public const PRIORITY_HIGH = 3;

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'tags' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(RoadmapVersion::class, 'version_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class)->orderBy('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('id');
    }

    public function linksAsSource(): HasMany
    {
        return $this->hasMany(TaskLink::class, 'source_task_id');
    }

    public function linksAsTarget(): HasMany
    {
        return $this->hasMany(TaskLink::class, 'target_task_id');
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(RoadmapTheme::class, 'theme_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
