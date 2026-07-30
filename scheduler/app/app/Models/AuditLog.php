<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only record of consequential actions. Nothing in the application
 * updates or deletes a row once written — `record()` is the only writer.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'team_id',
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'context',
        'ip',
    ];

    protected function casts(): array
    {
        return ['context' => 'array', 'created_at' => 'datetime'];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(
        string $action,
        ?Model $subject = null,
        array $context = [],
        ?int $teamId = null,
        ?int $userId = null,
    ): self {
        $user = auth()->user();

        return static::create([
            'team_id'      => $teamId ?? $user?->currentTeam?->id,
            'user_id'      => $userId ?? $user?->id,
            'action'       => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id'   => $subject?->getKey(),
            'context'      => $context ?: null,
            'ip'           => request()?->ip(),
        ]);
    }
}
