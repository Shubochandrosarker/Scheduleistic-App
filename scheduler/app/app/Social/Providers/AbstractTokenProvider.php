<?php

namespace App\Social\Providers;

use App\Models\Channel;
use App\Social\Contracts\SocialProvider;
use App\Social\Data\ConnectedAccount;

/**
 * Base for networks that connect with a token / app-password rather than the
 * OAuth2 authorization-code dance. The "connect" UI collects credentials which
 * are passed through exchangeCode().
 */
abstract class AbstractTokenProvider implements SocialProvider
{
    abstract public function key(): string;

    abstract public function label(): string;

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        // Credential-based providers are connected via a form, not a redirect.
        return route('workspaces.index');
    }

    public function exchangeCode(string $code, string $redirectUri): ConnectedAccount
    {
        // For these providers, $code is the supplied access token / credential.
        return new ConnectedAccount(
            providerAccountId: $this->key().'-'.substr(sha1($code), 0, 8),
            name: $this->label(),
            accessToken: $code,
            scopes: [],
        );
    }

    public function refreshToken(Channel $channel): void
    {
        // Token credentials don't expire via refresh; reconnect on failure.
    }

    public function rateLimit(): array
    {
        return ['max' => 30, 'per_seconds' => 60];
    }

    /** @return array<string, int> */
    public function fetchMetrics(Channel $channel, string $providerPostId): array
    {
        return [];
    }
}
