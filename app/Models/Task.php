<?php

namespace App\Models;

use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'project_id',
    'version_id',
    'theme_id',
    'okr_objective_id',
    'assigned_to',
    'title',
    'body',
    'status',
    'position',
    'priority',
    'due_date',
    'starts_at',
    'ends_at',
    'planning_status',
    'value_level',
    'effort_level',
    'eisenhower_quadrant',
    'tags',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    public const KANBAN_STATUSES = ['backlog', 'todo', 'in_progress', 'in_review', 'done'];

    public const PLANNING_ON_TIME = 'on_time';

    public const PLANNING_IN_PROGRESS = 'in_progress';

    public const PLANNING_NOT_STARTED = 'not_started';

    public const PLANNING_BEHIND = 'behind';

    public const PLANNING_BLOCKED = 'blocked';

    /**
     * @var list<string>
     */
    public const PLANNING_STATUSES = [
        self::PLANNING_ON_TIME,
        self::PLANNING_IN_PROGRESS,
        self::PLANNING_NOT_STARTED,
        self::PLANNING_BEHIND,
        self::PLANNING_BLOCKED,
    ];

    public const PRIORITY_LOW = 1;

    public const PRIORITY_NORMAL = 2;

    public const PRIORITY_HIGH = 3;

    public const MATRIX_LOW = 'low';

    public const MATRIX_MEDIUM = 'medium';

    public const MATRIX_HIGH = 'high';

    /**
     * @var list<string>
     */
    public const MATRIX_LEVELS = [
        self::MATRIX_LOW,
        self::MATRIX_MEDIUM,
        self::MATRIX_HIGH,
    ];

    public const EISENHOWER_DO_FIRST = 'do_first';

    public const EISENHOWER_SCHEDULE = 'schedule';

    public const EISENHOWER_DELEGATE = 'delegate';

    public const EISENHOWER_ELIMINATE = 'eliminate';

    /**
     * @var list<string>
     */
    public const EISENHOWER_QUADRANTS = [
        self::EISENHOWER_DO_FIRST,
        self::EISENHOWER_SCHEDULE,
        self::EISENHOWER_DELEGATE,
        self::EISENHOWER_ELIMINATE,
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'starts_at' => 'date',
            'ends_at' => 'date',
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

    public function okrObjective(): BelongsTo
    {
        return $this->belongsTo(OkrObjective::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function initiativeStart(): ?Carbon
    {
        if ($this->starts_at) {
            return $this->starts_at;
        }
        if ($this->ends_at) {
            return $this->ends_at->copy()->subWeeks(2);
        }
        if ($this->due_date) {
            return $this->due_date->copy()->subWeeks(2);
        }

        return null;
    }

    public function initiativeEnd(): ?Carbon
    {
        if ($this->ends_at) {
            return $this->ends_at;
        }
        if ($this->due_date) {
            return $this->due_date;
        }
        if ($this->starts_at) {
            return $this->starts_at->copy()->addWeeks(2);
        }

        return null;
    }
}
