<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single connected social account inside a Workspace.
 * OAuth tokens are encrypted at rest via the `encrypted` casts.
 */
class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'provider',
        'provider_account_id',
        'name',
        'avatar',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'meta',
        'status',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token'     => 'encrypted',
            'refresh_token'    => 'encrypted',
            'token_expires_at' => 'datetime',
            'scopes'           => 'array',
            'meta'             => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
