<?php

namespace App\Social\Providers;

use App\Models\Channel;
use App\Social\Data\ConnectedAccount;
use App\Social\Data\PublishPayload;
use App\Social\Data\PublishResult;
use App\Social\Exceptions\PublishException;
use Illuminate\Support\Arr;
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

    protected function mapAccount(array $tokenResponse): ConnectedAccount
    {
        return new ConnectedAccount(
            providerAccountId: (string) Arr::get($tokenResponse, 'open_id', 'tiktok'),
            name: 'TikTok',
            accessToken: (string) Arr::get($tokenResponse, 'access_token'),
            refreshToken: Arr::get($tokenResponse, 'refresh_token'),
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
                'post_info'   => ['title' => $payload->content, 'privacy_level' => 'SELF_ONLY'],
                'source_info' => ['source' => 'PULL_FROM_URL', 'video_url' => $video],
            ]);

        if ($response->failed()) {
            throw new PublishException("TikTok publish failed: {$response->body()}", retryable: $response->serverError());
        }

        return new PublishResult((string) Arr::get($response->json(), 'data.publish_id', ''), $response->json() ?? []);
    }
}
