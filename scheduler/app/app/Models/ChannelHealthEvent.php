<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One observation about a connected profile's health. Append-only: a state
 * that recovers gets `resolved_at` stamped by the service rather than the row
 * being rewritten, so the timeline stays intact.
 */
class ChannelHealthEvent extends Model
{
    public const STATE_CONNECTED = 'connected';
    public const STATE_TOKEN_EXPIRING = 'token_expiring';
    public const STATE_TOKEN_EXPIRED = 'token_expired';
    public const STATE_PERMISSION_MISSING = 'permission_missing';
    public const STATE_PROVIDER_UNAVAILABLE = 'provider_unavailable';
    public const STATE_RATE_LIMITED = 'rate_limited';
    public const STATE_PUBLISH_FAILED = 'publish_failed';

    /** States that mean the profile cannot publish right now. */
    public const BLOCKING_STATES = [
        self::STATE_TOKEN_EXPIRED,
        self::STATE_PERMISSION_MISSING,
    ];

    protected $fillable = [
        'channel_id', 'state', 'severity', 'message', 'context', 'detected_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'context'     => 'array',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
