<?php

namespace App\Social\Providers;

use App\Models\Channel;
use App\Social\Data\PublishPayload;
use App\Social\Data\PublishResult;
use App\Social\Exceptions\PublishException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/** Mastodon toot publishing. The instance base URL is stored in channel meta. */
class MastodonProvider extends AbstractTokenProvider
{
    public function key(): string
    {
        return 'mastodon';
    }

    public function label(): string
    {
        return 'Mastodon';
    }

    public function publish(Channel $channel, PublishPayload $payload): PublishResult
    {
        $instance = rtrim($channel->meta['instance'] ?? 'https://mastodon.social', '/');

        $response = Http::withToken($channel->access_token)
            ->post("{$instance}/api/v1/statuses", ['status' => $payload->content]);

        if ($response->failed()) {
            throw new PublishException("Mastodon publish failed: {$response->body()}", retryable: $response->serverError());
        }

        return new PublishResult((string) Arr::get($response->json(), 'id', ''), $response->json() ?? []);
    }

    public function fetchMetrics(Channel $channel, string $providerPostId): array
    {
        $instance = rtrim($channel->meta['instance'] ?? 'https://mastodon.social', '/');
        $data = Http::withToken($channel->access_token)->get("{$instance}/api/v1/statuses/{$providerPostId}")->json();

        return [
            'likes'  => (int) Arr::get($data, 'favourites_count', 0),
            'shares' => (int) Arr::get($data, 'reblogs_count', 0),
            'replies'=> (int) Arr::get($data, 'replies_count', 0),
        ];
    }
}
