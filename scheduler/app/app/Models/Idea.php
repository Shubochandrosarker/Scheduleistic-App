<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A content idea moving through the pipeline. Converting one produces posts;
 * the idea keeps a link to them so the board can show what came of it.
 */
class Idea extends Model
{
    use BelongsToWorkspace;
    use HasFactory;
    use SoftDeletes;

    public const STAGES = [
        'inbox', 'researching', 'drafting', 'ready', 'scheduled', 'published', 'archived',
    ];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $fillable = [
        'workspace_id',
        'campaign_id',
        'content_pillar_id',
        'author_id',
        'assignee_id',
        'title',
        'notes',
        'stage',
        'priority',
        'source_url',
        'attachments',
        'due_on',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'due_on'      => 'date',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(ContentPillar::class, 'content_pillar_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'idea_tag')->withTimestamps();
    }

    /** Posts produced by converting this idea. */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
