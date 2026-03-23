<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['source_task_id', 'target_task_id', 'type'])]
class TaskLink extends Model
{
    public const TYPE_RELATES = 'relates';

    public const TYPE_BLOCKS = 'blocks';

    public function source(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'source_task_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'target_task_id');
    }
}
