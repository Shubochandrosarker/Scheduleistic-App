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

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function pillars(): HasMany
    {
        return $this->hasMany(ContentPillar::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function ideas(): HasMany
    {
        return $this->hasMany(Idea::class);
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function mediaFolders(): HasMany
    {
        return $this->hasMany(MediaFolder::class);
    }

    /**
     * Seed the default content pillars. Called when a workspace is created so
     * the planner has something to categorise with on day one; a workspace that
     * already has pillars is left alone, which keeps this safe to re-run.
     */
    public function seedDefaultPillars(): void
    {
        if ($this->pillars()->exists()) {
            return;
        }

        foreach (ContentPillar::DEFAULTS as $position => $pillar) {
            $this->pillars()->create([...$pillar, 'position' => $position]);
        }
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
