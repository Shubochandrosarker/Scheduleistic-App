<?php

namespace App\Models;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A client / brand workspace inside an organization (Jetstream team).
 */
class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'client_name',
        'timezone',
        'color',
        'logo',
    ];

    /** The organization (Jetstream team) that owns this workspace. */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** Connected social accounts for this workspace. */
    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    /** Posting-time templates used by the queue. */
    public function timeSlots(): HasMany
    {
        return $this->hasMany(TimeSlot::class);
    }

    /** RSS / WordPress article feeds that auto-draft posts. */
    public function feeds(): HasMany
    {
        return $this->hasMany(Feed::class);
    }

    /** Whether a user is assigned to this workspace (any role). */
    public function hasUser(int $userId): bool
    {
        return $this->users()->where('users.id', $userId)->exists();
    }

    /** The workspace-level role for a user, if assigned. */
    public function roleFor(int $userId): ?string
    {
        return $this->users()->where('users.id', $userId)->first()?->pivot?->role;
    }

    /** Users assigned to this workspace, with their workspace role. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }
}
