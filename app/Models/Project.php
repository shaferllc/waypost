<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'description', 'url', 'archived_at'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ProjectInvitation::class);
    }

    public function shareTokens(): HasMany
    {
        return $this->hasMany(ProjectShareToken::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(ProjectWebhook::class);
    }

    public function themes(): HasMany
    {
        return $this->hasMany(RoadmapTheme::class)->orderBy('sort_order')->orderBy('id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)
            ->orderByRaw("case status when 'backlog' then 1 when 'todo' then 2 when 'in_progress' then 3 when 'in_review' then 4 when 'done' then 5 else 6 end")
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

    public function scopeAccessible(Builder $query, User $user): void
    {
        $query->where(function (Builder $q) use ($user): void {
            $q->where('user_id', $user->id)
                ->orWhereHas(
                    'members',
                    fn (Builder $m) => $m->where('user_id', $user->id)
                );
        });
    }

    public function scopeNotArchived(Builder $query): void
    {
        $query->whereNull('archived_at');
    }
}
