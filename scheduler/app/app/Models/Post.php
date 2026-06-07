<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasFactory;

    public const STATUS_DRAFT             = 'draft';
    public const STATUS_PENDING_APPROVAL  = 'pending_approval';
    public const STATUS_SCHEDULED         = 'scheduled';
    public const STATUS_PUBLISHING        = 'publishing';
    public const STATUS_PUBLISHED         = 'published';
    public const STATUS_PARTIALLY_FAILED  = 'partially_failed';
    public const STATUS_FAILED            = 'failed';

    protected $fillable = [
        'group_id',
        'workspace_id',
        'author_id',
        'status',
        'content',
        'media',
        'source',
        'scheduled_at',
        'timezone',
        'approval_state',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'media'        => 'array',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Post $post) {
            $post->group_id ??= (string) Str::uuid();
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PostVersion::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(PostTarget::class);
    }

    /** Content for a given provider, falling back to the post's base content. */
    public function contentFor(string $provider): ?string
    {
        $version = $this->versions->firstWhere('provider', $provider);

        return $version?->content ?? $this->content;
    }

    /** Recompute the post status from its targets' outcomes. */
    public function syncStatusFromTargets(): void
    {
        $targets = $this->targets()->get();

        if ($targets->isEmpty()) {
            return;
        }

        $statuses = $targets->pluck('status');

        $this->status = match (true) {
            $statuses->every(fn ($s) => $s === PostTarget::STATUS_PUBLISHED) => self::STATUS_PUBLISHED,
            $statuses->every(fn ($s) => $s === PostTarget::STATUS_FAILED)    => self::STATUS_FAILED,
            $statuses->contains(PostTarget::STATUS_PUBLISHED)
                && $statuses->contains(PostTarget::STATUS_FAILED)           => self::STATUS_PARTIALLY_FAILED,
            default                                                          => self::STATUS_PUBLISHING,
        };

        if ($this->status === self::STATUS_PUBLISHED && ! $this->published_at) {
            $this->published_at = now();
        }

        $this->save();
    }
}
