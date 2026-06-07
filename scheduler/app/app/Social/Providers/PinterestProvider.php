<?php

namespace App\Social\Providers;

use App\Models\Channel;
use App\Social\Data\ConnectedAccount;
use App\Social\Data\PublishPayload;
use App\Social\Data\PublishResult;
use App\Social\Exceptions\PublishException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/** Pinterest pin creation. A board id (channel meta) and an image are required. */
class PinterestProvider extends AbstractOAuthProvider
{
    public function key(): string
    {
        return 'pinterest';
    }

    public function label(): string
    {
        return 'Pinterest';
    }

    protected function authorizeEndpoint(): string
    {
        return 'https://www.pinterest.com/oauth/';
    }

    protected function tokenEndpoint(): string
    {
        return 'https://api.pinterest.com/v5/oauth/token';
    }

    protected function scopes(): array
    {
        return ['boards:read', 'pins:read', 'pins:write'];
    }

    protected function mapAccount(array $tokenResponse): ConnectedAccount
    {
        return new ConnectedAccount(
            providerAccountId: (string) Arr::get($tokenResponse, 'user_id', 'pinterest'),
            name: 'Pinterest',
            accessToken: (string) Arr::get($tokenResponse, 'access_token'),
            refreshToken: Arr::get($tokenResponse, 'refresh_token'),
            scopes: $this->scopes(),
        );
    }

    public function publish(Channel $channel, PublishPayload $payload): PublishResult
    {
        $board = $channel->meta['board_id'] ?? null;
        $image = $payload->media[0]['url'] ?? null;

        if (! $board || ! $image) {
            throw new PublishException('Pinterest requires a board and an image.', retryable: false);
        }

        $response = Http::withToken($channel->access_token)->post('https://api.pinterest.com/v5/pins', [
            'board_id'    => $board,
            'description' => $payload->content,
            'media_source' => ['source_type' => 'image_url', 'url' => $image],
        ]);

        if ($response->failed()) {
            throw new PublishException("Pinterest publish failed: {$response->body()}", retryable: $response->serverError());
        }

        return new PublishResult((string) Arr::get($response->json(), 'id', ''), $response->json() ?? []);
    }
}
