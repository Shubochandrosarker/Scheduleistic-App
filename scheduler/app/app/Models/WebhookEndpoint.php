<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An outbound webhook receiver. The secret is encrypted at rest and never
 * leaves the server except as the HMAC signature on a delivery.
 */
class WebhookEndpoint extends Model
{
    use HasFactory;

    /** The events an endpoint may subscribe to. */
    public const EVENTS = [
        'post.created',
        'post.scheduled',
        'approval.requested',
        'post.approved',
        'publish.succeeded',
        'publish.failed',
        'comment.received',
        'report.generated',
    ];

    /** Consecutive failures before the endpoint is disabled automatically. */
    public const FAILURE_THRESHOLD = 10;

    protected $fillable = [
        'team_id', 'workspace_id', 'name', 'url', 'secret', 'secret_rotated_at',
        'events', 'is_active', 'consecutive_failures', 'disabled_at', 'disabled_reason',
    ];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'secret'            => 'encrypted',
            'events'            => 'array',
            'is_active'         => 'boolean',
            'secret_rotated_at' => 'datetime',
            'disabled_at'       => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WebhookEndpoint $endpoint) {
            $endpoint->secret ??= self::newSecret();
        });
    }

    public static function newSecret(): string
    {
        return 'whsec_'.Str::random(48);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function subscribesTo(string $event): bool
    {
        return $this->is_active
            && $this->disabled_at === null
            && in_array($event, $this->events ?? [], true);
    }

    /** Sign a payload the way receivers are told to verify it. */
    public function sign(string $body, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $this->secret);
    }

    public function rotateSecret(): string
    {
        $secret = self::newSecret();

        $this->forceFill([
            'secret'            => $secret,
            'secret_rotated_at' => now(),
        ])->save();

        return $secret;
    }
}
