<?php

namespace App\Social\Providers;

use App\Models\Channel;
use App\Social\Data\ConnectedAccount;
use App\Social\Data\PublishPayload;
use App\Social\Data\PublishResult;
use App\Social\Exceptions\PublishException;
use App\Social\Providers\Concerns\DetectsMediaKind;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Meta Threads publishing via the two-step container → publish flow. */
class ThreadsProvider extends AbstractOAuthProvider
{
    use DetectsMediaKind;

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
        $exchanged = $this->exchangeForLongLivedToken((string) Arr::get($tokenResponse, 'access_token'));

        return new ConnectedAccount(
            providerAccountId: (string) Arr::get($tokenResponse, 'user_id', 'threads'),
            name: 'Threads',
            accessToken: $exchanged['token'],
            expiresAt: $exchanged['expiresAt'],
            scopes: $this->scopes(),
            meta: ['threads_user_id' => Arr::get($tokenResponse, 'user_id')],
        );
    }

    /**
     * Threads has its own long-lived-token endpoint and grant type
     * (`th_exchange_token`), distinct from the `fb_exchange_token` grant
     * the main Facebook Graph API uses — it is not shared with
     * Facebook/Instagram via ExchangesMetaLongLivedToken. Unlike the Meta
     * grant it needs no `client_id`, only the app secret and the token.
     *
     * @return array{token: string, expiresAt: ?Carbon}
     */
    protected function exchangeForLongLivedToken(string $shortLivedToken): array
    {
        $response = Http::get('https://graph.threads.net/access_token', [
            'grant_type' => 'th_exchange_token',
            'client_secret' => $this->clientSecret(),
            'access_token' => $shortLivedToken,
        ]);

        if ($response->failed()) {
            Log::warning("Meta long-lived token exchange failed for threads: {$response->body()}");

            return ['token' => $shortLivedToken, 'expiresAt' => null];
        }

        $data = $response->json();

        return [
            'token' => (string) Arr::get($data, 'access_token', $shortLivedToken),
            'expiresAt' => isset($data['expires_in'])
                ? Carbon::now()->addSeconds((int) $data['expires_in'])
                : null,
        ];
    }

    public function publish(Channel $channel, PublishPayload $payload): PublishResult
    {
        $userId = $channel->meta['threads_user_id'] ?? $channel->provider_account_id;
        $media = $payload->media[0] ?? null;

        $body = match (true) {
            $media === null => ['media_type' => 'TEXT', 'text' => $payload->content],
            $this->mediaKind($media) === 'video' => [
                'media_type' => 'VIDEO', 'video_url' => $media['url'], 'text' => $payload->content,
            ],
            default => [
                'media_type' => 'IMAGE', 'image_url' => $media['url'], 'text' => $payload->content,
            ],
        };

        $container = Http::post(self::API."/{$userId}/threads", [...$body, 'access_token' => $channel->access_token]);

        if ($container->failed()) {
            throw new PublishException("Threads container failed: {$container->body()}", retryable: $container->serverError());
        }

        $publish = Http::post(self::API."/{$userId}/threads_publish", [
            'creation_id' => Arr::get($container->json(), 'id'),
            'access_token' => $channel->access_token,
        ]);

        if ($publish->failed()) {
            throw new PublishException("Threads publish failed: {$publish->body()}", retryable: $publish->serverError());
        }

        return new PublishResult((string) Arr::get($publish->json(), 'id', ''), $publish->json() ?? []);
    }
}
