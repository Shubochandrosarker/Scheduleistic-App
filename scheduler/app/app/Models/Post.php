<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PUBLISHING = 'publishing';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_PARTIALLY_FAILED = 'partially_failed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'group_id',
        'workspace_id',
        'campaign_id',
        'content_pillar_id',
        'author_id',
        'assignee_id',
        'idea_id',
        'status',
        'title',
        'content',
        'media',
        'source',
        'scheduled_at',
        'timezone',
        'recurring_rule',
        'parent_post_id',
        'approval_state',
        'published_at',
        'hashtags',
        'ai_quality_report',
    ];

    protected function casts(): array
    {
        return [
            'media' => 'array',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'hashtags' => 'array',
            'ai_quality_report' => 'array',
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

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(ContentPillar::class, 'content_pillar_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag')->withTimestamps();
    }

    /** Library assets attached to this post (base post and per-network overrides). */
    public function mediaAssets(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'media_asset_post')
            ->withPivot(['provider', 'position'])
            ->withTimestamps();
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
            $statuses->every(fn ($s) => $s === PostTarget::STATUS_FAILED) => self::STATUS_FAILED,
            $statuses->contains(PostTarget::STATUS_PUBLISHED)
                && $statuses->contains(PostTarget::STATUS_FAILED) => self::STATUS_PARTIALLY_FAILED,
            default => self::STATUS_PUBLISHING,
        };

        if ($this->status === self::STATUS_PUBLISHED && ! $this->published_at) {
            $this->published_at = now();
        }

        $this->save();
    }
}
