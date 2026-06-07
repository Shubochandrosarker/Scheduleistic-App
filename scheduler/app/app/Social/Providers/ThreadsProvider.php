<?php

namespace App\Social\Providers;

use App\Models\Channel;
use App\Social\Data\ConnectedAccount;
use App\Social\Data\PublishPayload;
use App\Social\Data\PublishResult;
use App\Social\Exceptions\PublishException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/** Meta Threads publishing via the two-step container → publish flow. */
class ThreadsProvider extends AbstractOAuthProvider
{
    protected const API = 'https://graph.threads.net/v1.0';

    public function key(): string
    {
        return 'threads';
    }

    public function label(): string
    {
        return 'Threads';
    }

    protected function authorizeEndpoint(): string
    {
        return 'https://threads.net/oauth/authorize';
    }

    protected function tokenEndpoint(): string
    {
        return self::API.'/oauth/access_token';
    }

    protected function scopes(): array
    {
        return ['threads_basic', 'threads_content_publish'];
    }

    protected function mapAccount(array $tokenResponse): ConnectedAccount
    {
        return new ConnectedAccount(
            providerAccountId: (string) Arr::get($tokenResponse, 'user_id', 'threads'),
            name: 'Threads',
            accessToken: (string) Arr::get($tokenResponse, 'access_token'),
            scopes: $this->scopes(),
            meta: ['threads_user_id' => Arr::get($tokenResponse, 'user_id')],
        );
    }

    public function publish(Channel $channel, PublishPayload $payload): PublishResult
    {
        $userId = $channel->meta['threads_user_id'] ?? $channel->provider_account_id;

        $container = Http::post(self::API."/{$userId}/threads", [
            'media_type'   => 'TEXT',
            'text'         => $payload->content,
            'access_token' => $channel->access_token,
        ]);

        if ($container->failed()) {
            throw new PublishException("Threads container failed: {$container->body()}", retryable: $container->serverError());
        }

        $publish = Http::post(self::API."/{$userId}/threads_publish", [
            'creation_id'  => Arr::get($container->json(), 'id'),
            'access_token' => $channel->access_token,
        ]);

        if ($publish->failed()) {
            throw new PublishException("Threads publish failed: {$publish->body()}", retryable: $publish->serverError());
        }

        return new PublishResult((string) Arr::get($publish->json(), 'id', ''), $publish->json() ?? []);
    }
}
