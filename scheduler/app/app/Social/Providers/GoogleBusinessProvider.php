<?php

namespace App\Social\Providers;

use App\Models\Channel;
use App\Social\Data\ConnectedAccount;
use App\Social\Data\PublishPayload;
use App\Social\Data\PublishResult;
use App\Social\Exceptions\PublishException;
use App\Social\Providers\Concerns\SwitchesAvailableAccount;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Google Business Profile local posts. Posts are created against a location
 * resolved during the connect flow (accounts/{aid}/locations/{lid}).
 */
class GoogleBusinessProvider extends AbstractOAuthProvider
{
    use SwitchesAvailableAccount;

    protected const API = 'https://mybusiness.googleapis.com/v4';

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
            'client_id' => $this->clientId(),
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => implode(' ', $this->scopes()),
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);
    }

    protected function mapAccount(array $tokenResponse): ConnectedAccount
    {
        $accessToken = (string) Arr::get($tokenResponse, 'access_token');
        $locations = $this->listLocations($accessToken);

        if ($locations === []) {
            throw new PublishException(
                'No Google Business Profile locations were found for this account. '
                    .'You must be a manager or owner of at least one location to connect it.',
                retryable: false,
            );
        }

        $chosen = $locations[0];

        return new ConnectedAccount(
            providerAccountId: (string) Arr::get($tokenResponse, 'sub', 'gbp'),
            name: (string) ($chosen['name'] ?? 'Google Business Profile'),
            accessToken: $accessToken,
            refreshToken: Arr::get($tokenResponse, 'refresh_token'),
            expiresAt: isset($tokenResponse['expires_in'])
                ? Carbon::now()->addSeconds((int) $tokenResponse['expires_in'])
                : null,
            scopes: $this->scopes(),
            meta: [
                'location_name' => $chosen['id'],
                // Every other location the user manages, for visibility and
                // for the channel-settings "switch location" control — safe
                // to store as-is because switching only ever needs this
                // account's own (already-stored) access token to re-resolve,
                // never a second credential.
                'available_accounts' => $locations,
            ],
        );
    }

    /**
     * Every location across every Business Profile account this user can
     * manage. Two calls deep (accounts, then each account's locations)
     * because that is the shape of the v4 Business Information API this
     * driver already publishes local posts through — not a newer API
     * generation this app doesn't otherwise use.
     *
     * @return array<int, array{id: string, name: string}>
     */
    protected function listLocations(string $accessToken): array
    {
        $accounts = (array) Arr::get(
            Http::withToken($accessToken)->get(self::API.'/accounts')->json(),
            'accounts',
            [],
        );

        $locations = [];

        foreach ($accounts as $account) {
            $accountName = Arr::get($account, 'name');

            if (! $accountName) {
                continue;
            }

            $accountLocations = (array) Arr::get(
                Http::withToken($accessToken)->get(self::API."/{$accountName}/locations")->json(),
                'locations',
                [],
            );

            foreach ($accountLocations as $location) {
                $locations[] = [
                    'id' => (string) Arr::get($location, 'name'),
                    'name' => (string) (Arr::get($location, 'locationName') ?? Arr::get($location, 'title') ?? 'Location'),
                ];
            }
        }

        return $locations;
    }

    public function publish(Channel $channel, PublishPayload $payload): PublishResult
    {
        $location = $channel->meta['location_name'] ?? null;

        if (! $location) {
            throw new PublishException('No Google Business location configured for this channel.', retryable: false);
        }

        $body = [
            'languageCode' => 'en-US',
            'summary' => $payload->content,
            'topicType' => 'STANDARD',
        ];

        // Only a single photo is supported (config/provider_capabilities.php
        // caps this network at max_images=1, max_videos=0).
        if ($image = $payload->media[0]['url'] ?? null) {
            $body['media'] = [['mediaFormat' => 'PHOTO', 'sourceUrl' => $image]];
        }

        $response = Http::withToken($channel->access_token)
            ->post(self::API."/{$location}/localPosts", $body);

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

    public function selectAccount(Channel $channel, string $accountId): void
    {
        $this->applyAccountSelection($channel, $accountId, 'location_name');
    }
}
