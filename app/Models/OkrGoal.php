<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['project_id', 'title', 'sort_order'])]
class OkrGoal extends Model
{
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function objectives(): HasMany
    {
        return $this->hasMany(OkrObjective::class)->orderBy('sort_order')->orderBy('id');
    }

    public function averageProgress(): int
    {
        $objectives = $this->objectives->loadMissing('keyResults');
        if ($objectives->isEmpty()) {
            return 0;
        }

        $sum = 0;
        $count = 0;
        foreach ($objectives as $objective) {
            $sum += $objective->averageProgress();
            $count++;
        }

        return $count > 0 ? (int) round($sum / $count) : 0;
    }
}
