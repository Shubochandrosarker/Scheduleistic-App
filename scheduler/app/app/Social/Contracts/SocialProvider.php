<?php

namespace App\Social\Contracts;

use App\Models\Channel;
use App\Social\Data\ConnectedAccount;
use App\Social\Data\PublishPayload;
use App\Social\Data\PublishResult;

/**
 * The contract every social network driver implements. Adding a new network
 * to Scheduleistic means writing one class that fulfils this interface and
 * registering it with the ProviderManager — nothing else changes.
 */
interface SocialProvider
{
    /** Stable provider key, e.g. "linkedin", "facebook". */
    public function key(): string;

    /** Human-readable label for the UI. */
    public function label(): string;

    // --- OAuth ------------------------------------------------------------

    /** Build the provider's authorization URL to start the connect flow. */
    public function authorizationUrl(string $state, string $redirectUri): string;

    /** Exchange an authorization code for tokens + account details. */
    public function exchangeCode(string $code, string $redirectUri): ConnectedAccount;

    /** Refresh an expiring access token in place on the channel. */
    public function refreshToken(Channel $channel): void;

    // --- Publishing -------------------------------------------------------

    /**
     * Publish the payload to the given channel.
     *
     * @throws \App\Social\Exceptions\PublishException on failure.
     */
    public function publish(Channel $channel, PublishPayload $payload): PublishResult;

    /**
     * Rate limit for this provider as ['max' => int, 'per_seconds' => int].
     *
     * @return array{max:int, per_seconds:int}
     */
    public function rateLimit(): array;

    /**
     * Fetch engagement metrics for a previously published post.
     *
     * @return array<string, int>  metric => value (e.g. ['likes' => 10, 'comments' => 2])
     */
    public function fetchMetrics(Channel $channel, string $providerPostId): array;
}
