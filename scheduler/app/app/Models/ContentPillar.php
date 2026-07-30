<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A content pillar is the *kind* of content a post is — education, promotion,
 * community — as opposed to the campaign it serves. Every workspace starts
 * with the default set (see PillarSeeder) and may add its own.
 */
class ContentPillar extends Model
{
    use BelongsToWorkspace;
    use HasFactory;

    /** The starter set created with every new workspace. */
    public const DEFAULTS = [
        ['name' => 'Education',   'color' => '#4F8DFF', 'target_share' => 30],
        ['name' => 'Promotion',   'color' => '#F59E0B', 'target_share' => 20],
        ['name' => 'Engagement',  'color' => '#22D3EE', 'target_share' => 20],
        ['name' => 'Community',   'color' => '#34D399', 'target_share' => 15],
        ['name' => 'Product',     'color' => '#A78BFA', 'target_share' => 10],
        ['name' => 'Testimonial', 'color' => '#F87171', 'target_share' => 5],
    ];

    protected $fillable = [
        'workspace_id',
        'name',
        'slug',
        'description',
        'color',
        'target_share',
        'position',
    ];

    protected static function booted(): void
    {
        static::saving(function (ContentPillar $pillar) {
            $pillar->slug ??= Str::slug($pillar->name);
        });
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
