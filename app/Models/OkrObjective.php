<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['okr_goal_id', 'title', 'sort_order'])]
class OkrObjective extends Model
{
    public function goal(): BelongsTo
    {
        return $this->belongsTo(OkrGoal::class, 'okr_goal_id');
    }

    public function keyResults(): HasMany
    {
        return $this->hasMany(OkrKeyResult::class)->orderBy('sort_order')->orderBy('id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function averageProgress(): int
    {
        $keyResults = $this->relationLoaded('keyResults')
            ? $this->keyResults
            : $this->keyResults()->get();

        if ($keyResults->isEmpty()) {
            return 0;
        }

        return (int) round($keyResults->avg('progress'));
    }
}
