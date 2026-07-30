<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    use BelongsToWorkspace;
    use HasFactory;

    protected $fillable = ['workspace_id', 'name', 'slug', 'color'];

    protected static function booted(): void
    {
        static::saving(function (Tag $tag) {
            $tag->slug ??= Str::slug($tag->name);
        });
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_tag')->withTimestamps();
    }

    public function ideas(): BelongsToMany
    {
        return $this->belongsToMany(Idea::class, 'idea_tag')->withTimestamps();
    }

    public function mediaAssets(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'media_asset_tag')->withTimestamps();
    }

    /** Find-or-create by name within one workspace, so tagging never duplicates. */
    public static function resolve(int $workspaceId, string $name): self
    {
        return static::firstOrCreate(
            ['workspace_id' => $workspaceId, 'slug' => Str::slug($name)],
            ['name' => trim($name)],
        );
    }
}
