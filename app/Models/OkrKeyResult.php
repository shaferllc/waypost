<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['okr_objective_id', 'title', 'progress', 'sort_order'])]
class OkrKeyResult extends Model
{
    public function objective(): BelongsTo
    {
        return $this->belongsTo(OkrObjective::class, 'okr_objective_id');
    }
}
