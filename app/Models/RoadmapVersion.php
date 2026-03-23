<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['project_id', 'name', 'description', 'target_date', 'released_at', 'release_notes', 'sort_order'])]
class RoadmapVersion extends Model
{
    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'released_at' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'version_id')->orderBy('position')->orderBy('id');
    }
}
