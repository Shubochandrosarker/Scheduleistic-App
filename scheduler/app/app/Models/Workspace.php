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

    /** Users assigned to this workspace, with their workspace role. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }
}
