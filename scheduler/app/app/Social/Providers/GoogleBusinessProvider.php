<?php

namespace App\Social\Providers;

use App\Models\Channel;
use App\Social\Data\ConnectedAccount;
use App\Social\Data\PublishPayload;
use App\Social\Data\PublishResult;
use App\Social\Exceptions\PublishException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Google Business Profile local posts. Posts are created against a location
 * resolved during the connect flow (accounts/{aid}/locations/{lid}).
 */
class GoogleBusinessProvider extends AbstractOAuthProvider
{
    public function key(): string
    {
        return 'google_business';
    }

    public function label(): string
    {
        return 'Google Business Profile';
    }

    protected function authorizeEndpoint(): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth';
    }

    protected function tokenEndpoint(): string
    {
        return 'https://oauth2.googleapis.com/token';
    }

    protected function scopes(): array
    {
        return ['https://www.googleapis.com/auth/business.manage'];
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        // Google needs access_type=offline + prompt=consent to return a refresh token.
        return $this->authorizeEndpoint().'?'.http_build_query([
            'response_type' => 'code',
            'client_id'     => $this->clientId(),
            'redirect_uri'  => $redirectUri,
            'state'         => $state,
            'scope'         => implode(' ', $this->scopes()),
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ]);
    }

    protected function mapAccount(array $tokenResponse): ConnectedAccount
    {
        return new ConnectedAccount(
            providerAccountId: (string) Arr::get($tokenResponse, 'sub', 'gbp'),
            name: 'Google Business Profile',
            accessToken: (string) Arr::get($tokenResponse, 'access_token'),
            refreshToken: Arr::get($tokenResponse, 'refresh_token'),
            expiresAt: isset($tokenResponse['expires_in'])
                ? Carbon::now()->addSeconds((int) $tokenResponse['expires_in'])
                : null,
            scopes: $this->scopes(),
        );
    }

    public function publish(Channel $channel, PublishPayload $payload): PublishResult
    {
        $location = $channel->meta['location_name'] ?? null;

        if (! $location) {
            throw new PublishException('No Google Business location configured for this channel.', retryable: false);
        }

        $response = Http::withToken($channel->access_token)
            ->post("https://mybusiness.googleapis.com/v4/{$location}/localPosts", [
                'languageCode' => 'en-US',
                'summary'      => $payload->content,
                'topicType'    => 'STANDARD',
            ]);

        if ($response->failed()) {
            throw new PublishException(
                "Google Business publish failed: {$response->status()} {$response->body()}",
                retryable: $response->status() === 429 || $response->serverError(),
            );
        }

        return new PublishResult(
            providerPostId: (string) Arr::get($response->json(), 'name', ''),
            raw: $response->json() ?? [],
        );
    }

    public function rateLimit(): array
    {
        return ['max' => 10, 'per_seconds' => 60];
    }
}
