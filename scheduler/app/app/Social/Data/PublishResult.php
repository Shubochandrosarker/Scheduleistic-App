<?php

namespace App\Social\Data;

/**
 * The outcome of a successful publish to a provider.
 */
class PublishResult
{
    /**
     * @param  array<string, mixed>  $raw  raw provider response (for logging/analytics)
     */
    public function __construct(
        public readonly string $providerPostId,
        public readonly array $raw = [],
    ) {}
}
