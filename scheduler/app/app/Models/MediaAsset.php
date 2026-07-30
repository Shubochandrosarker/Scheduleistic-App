<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * One uploaded asset. The row at `path` is the untouched original; everything
 * the app renders comes out of `variants`, which the queue fills in.
 */
class MediaAsset extends Model
{
    use BelongsToWorkspace;
    use HasFactory;
    use SoftDeletes;

    public const KIND_IMAGE = 'image';
    public const KIND_VIDEO = 'video';
    public const KIND_DOCUMENT = 'document';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'workspace_id',
        'media_folder_id',
        'uploaded_by',
        'name',
        'kind',
        'mime',
        'disk',
        'path',
        'size',
        'width',
        'height',
        'duration',
        'checksum',
        'alt_text',
        'notes',
        'source',
        'source_ref',
        'variants',
        'processing_status',
        'processing_error',
        'usage_count',
        'last_used_at',
        'is_favorite',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'variants'     => 'array',
            'is_favorite'  => 'boolean',
            'last_used_at' => 'datetime',
            'archived_at'  => 'datetime',
            'duration'     => 'float',
        ];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'media_folder_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'media_asset_tag')->withTimestamps();
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'media_asset_post')
            ->withPivot(['provider', 'position'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function isReady(): bool
    {
        return $this->processing_status === self::STATUS_READY;
    }

    /**
     * A temporary URL for the original. Signed on drivers that support it (S3),
     * falling back to the public URL on local disks. Never returns a raw path,
     * so a tenant cannot guess another tenant's object key from a response.
     */
    public function url(?string $variant = null): ?string
    {
        $path = $variant ? ($this->variants[$variant]['path'] ?? null) : $this->path;

        if (! $path) {
            return null;
        }

        $disk = Storage::disk($this->disk);

        try {
            return $disk->temporaryUrl($path, now()->addMinutes(30));
        } catch (\Throwable) {
            return $disk->url($path);
        }
    }

    /** Every stored object for this asset — the original plus its derivatives. */
    public function allPaths(): array
    {
        $paths = [$this->path];

        foreach ($this->variants ?? [] as $variant) {
            if (! empty($variant['path'])) {
                $paths[] = $variant['path'];
            }
        }

        return array_values(array_unique(array_filter($paths)));
    }
}
