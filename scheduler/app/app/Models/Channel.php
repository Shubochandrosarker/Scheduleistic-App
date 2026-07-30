<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single connected social account inside a Workspace.
 * OAuth tokens are encrypted at rest via the `encrypted` casts.
 */
class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'provider',
        'provider_account_id',
        'name',
        'avatar',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'meta',
        'status',
        'health_state',
        'last_published_at',
        'last_metrics_sync_at',
        'last_health_check_at',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * Mirrors the column default so a freshly created model reports its health
     * without a round trip. Reading `health_state` as null on a brand-new
     * channel would make `canPublish()` and the health summary disagree with
     * what the database holds.
     */
    protected $attributes = [
        'health_state' => 'connected',
    ];

    protected function casts(): array
    {
        return [
            'access_token'     => 'encrypted',
            'refresh_token'    => 'encrypted',
            'token_expires_at' => 'datetime',
            'scopes'               => 'array',
            'meta'                 => 'array',
            'last_published_at'    => 'datetime',
            'last_metrics_sync_at' => 'datetime',
            'last_health_check_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function healthEvents(): HasMany
    {
        return $this->hasMany(ChannelHealthEvent::class);
    }

    /** Unresolved health problems, newest first. */
    public function openHealthEvents(): HasMany
    {
        return $this->healthEvents()->whereNull('resolved_at')->latest('detected_at');
    }

    /** Whether this profile can publish right now. */
    public function canPublish(): bool
    {
        return ! in_array($this->health_state, ChannelHealthEvent::BLOCKING_STATES, true);
    }
}
