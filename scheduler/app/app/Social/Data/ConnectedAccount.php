<?php

namespace App\Social\Data;

/**
 * Normalized account info returned after an OAuth code exchange, used to
 * create or update a Channel.
 */
class ConnectedAccount
{
    /**
     * @param  array<int, string>  $scopes
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $providerAccountId,
        public readonly string $name,
        public readonly string $accessToken,
        public readonly ?string $refreshToken = null,
        public readonly ?\DateTimeInterface $expiresAt = null,
        public readonly ?string $avatar = null,
        public readonly array $scopes = [],
        public readonly array $meta = [],
    ) {}
}
