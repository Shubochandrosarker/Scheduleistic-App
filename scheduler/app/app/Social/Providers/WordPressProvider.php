<?php

namespace App\Social\Providers;

use App\Models\Channel;
use App\Social\Data\PublishPayload;
use App\Social\Data\PublishResult;
use App\Social\Exceptions\PublishException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * WordPress publishing via the REST API using an application password.
 * The channel meta holds the site URL and username; access_token is the app
 * password. This is the outbound side of the WORDPRESSISTIC integration.
 */
class WordPressProvider extends AbstractTokenProvider
{
    public function key(): string
    {
        return 'wordpress';
    }

    public function label(): string
    {
        return 'WordPress';
    }

    public function publish(Channel $channel, PublishPayload $payload): PublishResult
    {
        $site = rtrim($channel->meta['site_url'] ?? '', '/');
        $user = $channel->meta['username'] ?? '';

        if (! $site || ! $user) {
            throw new PublishException('WordPress site URL and username are required.', retryable: false);
        }

        $response = Http::withBasicAuth($user, $channel->access_token)
            ->post("{$site}/wp-json/wp/v2/posts", [
                'title'   => mb_substr(strtok($payload->content, "\n"), 0, 120),
                'content' => $payload->content,
                'status'  => 'publish',
            ]);

        if ($response->failed()) {
            throw new PublishException("WordPress publish failed: {$response->body()}", retryable: $response->serverError());
        }

        return new PublishResult((string) Arr::get($response->json(), 'id', ''), $response->json() ?? []);
    }
}
