<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends Model
{
    use HasFactory;

    public const STATE_PENDING           = 'pending';
    public const STATE_APPROVED          = 'approved';
    public const STATE_REJECTED          = 'rejected';
    public const STATE_CHANGES_REQUESTED = 'changes_requested';

    protected $fillable = [
        'post_id',
        'requested_by',
        'decided_by',
        'state',
        'comment',
        'decided_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
