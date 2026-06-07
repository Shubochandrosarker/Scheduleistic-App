<?php

namespace App\Social\Providers;

use App\Models\Channel;
use App\Social\Data\PublishPayload;
use App\Social\Data\PublishResult;
use App\Social\Exceptions\PublishException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * Bluesky (AT Protocol) posting. The channel stores the handle + an app
 * password in meta; we mint a session then create a feed post record.
 */
class BlueskyProvider extends AbstractTokenProvider
{
    protected const PDS = 'https://bsky.social/xrpc';

    public function key(): string
    {
        return 'bluesky';
    }

    public function label(): string
    {
        return 'Bluesky';
    }

    public function publish(Channel $channel, PublishPayload $payload): PublishResult
    {
        $session = Http::post(self::PDS.'/com.atproto.server.createSession', [
            'identifier' => $channel->meta['handle'] ?? $channel->name,
            'password'   => $channel->access_token, // app password
        ]);

        if ($session->failed()) {
            throw new PublishException('Bluesky auth failed.', retryable: false);
        }

        $jwt = Arr::get($session->json(), 'accessJwt');
        $did = Arr::get($session->json(), 'did');

        $response = Http::withToken($jwt)->post(self::PDS.'/com.atproto.repo.createRecord', [
            'repo'       => $did,
            'collection' => 'app.bsky.feed.post',
            'record'     => ['text' => $payload->content, 'createdAt' => now()->toIso8601String()],
        ]);

        if ($response->failed()) {
            throw new PublishException("Bluesky publish failed: {$response->body()}", retryable: $response->serverError());
        }

        return new PublishResult((string) Arr::get($response->json(), 'uri', ''), $response->json() ?? []);
    }
}
