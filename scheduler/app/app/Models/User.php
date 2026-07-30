<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * Note: `is_platform_admin` is deliberately NOT mass-assignable — it is a
     * privilege flag set only via seeders/console to prevent escalation.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

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
            'is_platform_admin' => 'boolean',
        ];
    }

    /** Workspaces this user is individually assigned to (incl. client portal). */
    public function workspaces(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** Per-event notification delivery preferences. */
    public function notificationPreferences(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /**
     * Every workspace this user may see: those owned by their current
     * organization, plus any they are individually assigned to (client portal).
     *
     * This is *the* tenancy boundary for workspace-scoped data. Controllers
     * intersect request-supplied ids with this set rather than trusting them,
     * which is why it lives on the model instead of being re-derived per
     * controller.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function visibleWorkspaceIds(): \Illuminate\Support\Collection
    {
        $owned    = $this->currentTeam?->workspaces()->pluck('id') ?? collect();
        $assigned = $this->workspaces()->pluck('workspaces.id');

        return $owned->merge($assigned)->unique()->values();
    }

    /** Whether the user may act on a specific workspace id. */
    public function canAccessWorkspace(int $workspaceId): bool
    {
        return $this->visibleWorkspaceIds()->contains($workspaceId);
    }
}
