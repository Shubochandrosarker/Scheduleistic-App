<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feed extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'url',
        'name',
        'active',
        'seen_guids',
        'last_polled_at',
    ];

    protected function casts(): array
    {
        return [
            'active'         => 'boolean',
            'seen_guids'     => 'array',
            'last_polled_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
