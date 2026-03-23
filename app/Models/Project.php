<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'description', 'url'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)
            ->orderByRaw("case status when 'backlog' then 1 when 'todo' then 2 when 'in_progress' then 3 when 'done' then 4 else 5 end")
            ->orderBy('position')
            ->orderBy('id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(ProjectLink::class)->orderBy('id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RoadmapVersion::class)->orderBy('sort_order')->orderBy('target_date')->orderBy('id');
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
