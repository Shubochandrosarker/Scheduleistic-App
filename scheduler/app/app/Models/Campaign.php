<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A campaign groups posts, ideas and reporting around one push — a launch, a
 * seasonal promotion, an always-on content stream.
 */
class Campaign extends Model
{
    use BelongsToWorkspace;
    use HasFactory;
    use SoftDeletes;

    public const STATUSES = ['planning', 'active', 'paused', 'completed', 'archived'];

    protected $fillable = [
        'workspace_id',
        'owner_id',
        'name',
        'slug',
        'description',
        'goal',
        'status',
        'starts_on',
        'ends_on',
        'budget_cents',
        'currency',
        'target_url',
        'utm',
        'kpi_targets',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'starts_on'   => 'date',
            'ends_on'     => 'date',
            'utm'         => 'array',
            'kpi_targets' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Campaign $campaign) {
            $campaign->slug ??= Str::slug($campaign->name).'-'.Str::lower(Str::random(5));
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'campaign_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function ideas(): HasMany
    {
        return $this->hasMany(Idea::class);
    }

    /**
     * The UTM query string this campaign appends to tracked links. Values are
     * urlencoded here rather than at the call site so every consumer — the
     * composer preview, the link shortener, the WordPress adapter — produces
     * byte-identical URLs and attribution does not fragment.
     */
    public function utmQuery(): string
    {
        $utm = collect($this->utm ?? [])
            ->only(['source', 'medium', 'campaign', 'term', 'content'])
            ->filter(fn ($v) => filled($v))
            ->mapWithKeys(fn ($v, $k) => ["utm_{$k}" => $v]);

        if ($utm->isEmpty()) {
            return '';
        }

        return http_build_query($utm->all());
    }
}
