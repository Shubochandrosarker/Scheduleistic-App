<?php

namespace App\Social\Providers;

use App\Models\Channel;
use App\Social\Contracts\SocialProvider;
use App\Social\Data\ConnectedAccount;
use App\Social\Data\PublishPayload;
use App\Social\Data\PublishResult;
use App\Social\Exceptions\PublishException;
use Illuminate\Support\Str;

/**
 * A no-network provider used for local development and tests, and as the
 * driver when SOCIAL_FAKE=true. It simulates success, and can be told to
 * fail on demand to exercise the engine's retry/failure paths.
 */
class FakeProvider implements SocialProvider
{
    public static bool $shouldFail = false;
    public static bool $failRetryable = true;

    public function __construct(private readonly string $key = 'fake') {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return 'Fake Provider';
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return $redirectUri.'?code=fake-code&state='.$state;
    }

    public function exchangeCode(string $code, string $redirectUri): ConnectedAccount
    {
        return new ConnectedAccount(
            providerAccountId: 'fake-'.Str::random(8),
            name: 'Fake Account',
            accessToken: 'fake-access-token',
            refreshToken: 'fake-refresh-token',
        );
    }

    public function refreshToken(Channel $channel): void
    {
        $channel->update(['access_token' => 'fake-access-token-refreshed', 'status' => 'connected']);
    }

    public function publish(Channel $channel, PublishPayload $payload): PublishResult
    {
        if (static::$shouldFail) {
            throw new PublishException('Simulated failure', retryable: static::$failRetryable);
        }

        return new PublishResult(
            providerPostId: 'fake-post-'.Str::random(10),
            raw: ['content' => $payload->content],
        );
    }

    public function rateLimit(): array
    {
        return ['max' => 1000, 'per_seconds' => 60];
    }

    /** @return array<string, int> */
    public function fetchMetrics(Channel $channel, string $providerPostId): array
    {
        return ['impressions' => 100, 'likes' => 10, 'comments' => 2, 'shares' => 1];
    }
}
