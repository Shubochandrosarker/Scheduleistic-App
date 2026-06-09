<?php

namespace App\Social\Providers;

use App\Models\Channel;
use App\Social\Contracts\SocialProvider;
use App\Social\Data\ConnectedAccount;
use Illuminate\Support\Str;

/**
 * Base for networks that connect with a token / app-password rather than the
 * OAuth2 authorization-code dance. The "connect" UI collects the fields from
 * fields() and POSTs them; accountFromCredentials() turns them into a channel.
 */
abstract class AbstractTokenProvider implements SocialProvider
{
    abstract public function key(): string;

    abstract public function label(): string;

    /**
     * Credential fields the connect form should collect.
     *
     * @return array<int, array{key:string, label:string, type:string}>
     */
    public function fields(): array
    {
        return [
            ['key' => 'access_token', 'label' => 'Access token', 'type' => 'password'],
        ];
    }

    /**
     * Build a connected account from the submitted credential fields.
     *
     * @param  array<string, string>  $input
     */
    public function accountFromCredentials(array $input): ConnectedAccount
    {
        $token = $input['access_token'] ?? '';

        return new ConnectedAccount(
            providerAccountId: $this->key().'-'.substr(sha1($token ?: Str::random(16)), 0, 8),
            name: $this->label(),
            accessToken: $token,
            scopes: [],
        );
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        // Credential-based providers are connected via a form, not a redirect.
        return route('workspaces.index');
    }

    public function exchangeCode(string $code, string $redirectUri): ConnectedAccount
    {
        // For these providers, $code is the supplied access token / credential.
        return $this->accountFromCredentials(['access_token' => $code]);
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
