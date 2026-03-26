<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'provider', 'provider_id', 'email_verified_at', 'email_login_code_enabled', 'email_login_magic_link_enabled'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'magic_link_sign_in_pending_token_hash', 'email_code_sign_in_pending_token_hash'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)->latest();
    }

    public function sharedProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null
            && is_string($this->two_factor_secret)
            && $this->two_factor_secret !== '';
    }

    public function hasPendingTwoFactorSetup(): bool
    {
        return ! $this->hasTwoFactorEnabled()
            && is_string($this->two_factor_secret)
            && $this->two_factor_secret !== '';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'email_login_code_enabled' => 'boolean',
            'email_login_magic_link_enabled' => 'boolean',
            'magic_link_sign_in_pending_expires_at' => 'datetime',
            'email_code_sign_in_pending_expires_at' => 'datetime',
        ];
    }
}
