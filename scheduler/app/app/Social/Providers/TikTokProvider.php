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

/** TikTok video publishing via the Content Posting API (PULL_FROM_URL). */
class TikTokProvider extends AbstractOAuthProvider
{
    public function key(): string
    {
        return 'tiktok';
    }

    public function label(): string
    {
        return 'TikTok';
    }

    protected function authorizeEndpoint(): string
    {
        return 'https://www.tiktok.com/v2/auth/authorize/';
    }

    protected function tokenEndpoint(): string
    {
        return 'https://open.tiktokapis.com/v2/oauth/token/';
    }

    protected function scopes(): array
    {
        return ['user.info.basic', 'video.publish'];
    }

    /**
     * TikTok's v2 API names the app-identifying parameter `client_key`, not
     * the `client_id` every other OAuth2 provider (and the generic base
     * class) uses. Sending `client_id` is rejected regardless of how correct
     * the credential value is, so every OAuth entry point is overridden here
     * rather than customizing the shared base class for one provider's
     * naming quirk — the same approach GoogleBusinessProvider already uses
     * to add its own extra authorize params.
     */
    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return $this->authorizeEndpoint().'?'.http_build_query([
            'client_key' => $this->clientId(),
            'response_type' => 'code',
            // TikTok documents comma-separated scopes, unlike the
            // space-separated form most other OAuth2 providers use.
            'scope' => implode(',', $this->scopes()),
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code, string $redirectUri): ConnectedAccount
    {
        $response = Http::asForm()->post($this->tokenEndpoint(), [
            'client_key' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]);

        if ($response->failed()) {
            throw new PublishException(
                'OAuth token exchange failed for tiktok: '.$response->body(),
                retryable: false,
            );
        }

        return $this->mapAccount($response->json());
    }

    public function refreshToken(Channel $channel): void
    {
        if (! $channel->refresh_token) {
            return;
        }

        $response = Http::asForm()->post($this->tokenEndpoint(), [
            'client_key' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'grant_type' => 'refresh_token',
            'refresh_token' => $channel->refresh_token,
        ]);

        if ($response->failed()) {
            $channel->update(['status' => 'expired']);

            return;
        }

        $data = $response->json();

        $channel->update([
            'access_token' => Arr::get($data, 'access_token', $channel->access_token),
            'refresh_token' => Arr::get($data, 'refresh_token', $channel->refresh_token),
            'token_expires_at' => isset($data['expires_in'])
                ? Carbon::now()->addSeconds((int) $data['expires_in'])
                : $channel->token_expires_at,
            'status' => 'connected',
        ]);
    }

    protected function mapAccount(array $tokenResponse): ConnectedAccount
    {
        return new ConnectedAccount(
            providerAccountId: (string) Arr::get($tokenResponse, 'open_id', 'tiktok'),
            name: 'TikTok',
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
        $video = $payload->media[0]['url'] ?? null;

        if (! $video) {
            throw new PublishException('TikTok requires a video URL.', retryable: false);
        }

        $response = Http::withToken($channel->access_token)
            ->post('https://open.tiktokapis.com/v2/post/publish/video/init/', [
                'post_info' => ['title' => $payload->content, 'privacy_level' => 'SELF_ONLY'],
                'source_info' => ['source' => 'PULL_FROM_URL', 'video_url' => $video],
            ]);

        if ($response->failed()) {
            throw new PublishException("TikTok publish failed: {$response->body()}", retryable: $response->serverError());
        }

        return new PublishResult((string) Arr::get($response->json(), 'data.publish_id', ''), $response->json() ?? []);
    }
}
