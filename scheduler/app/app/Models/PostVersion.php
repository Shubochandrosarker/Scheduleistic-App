<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'provider',
        'content',
        'media',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'media'   => 'array',
            'options' => 'array',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
