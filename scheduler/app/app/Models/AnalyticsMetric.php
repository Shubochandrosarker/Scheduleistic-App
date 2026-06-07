<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_target_id',
        'channel_id',
        'metric',
        'value',
        'captured_at',
    ];

    protected function casts(): array
    {
        return ['captured_at' => 'datetime'];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function postTarget(): BelongsTo
    {
        return $this->belongsTo(PostTarget::class);
    }
}
