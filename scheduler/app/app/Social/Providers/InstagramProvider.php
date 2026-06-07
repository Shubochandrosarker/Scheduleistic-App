<?php

namespace App\Social\Providers;

use App\Models\Channel;
use App\Social\Data\ConnectedAccount;
use App\Social\Data\PublishPayload;
use App\Social\Data\PublishResult;
use App\Social\Exceptions\PublishException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * Instagram (Business/Creator) publishing via the Graph API two-step flow:
 * create a media container, then publish it. Requires at least one image.
 */
class InstagramProvider extends AbstractOAuthProvider
{
    protected const GRAPH = 'https://graph.facebook.com/v21.0';

    public function key(): string
    {
        return 'instagram';
    }

    public function label(): string
    {
        return 'Instagram';
    }

    protected function authorizeEndpoint(): string
    {
        return 'https://www.facebook.com/v21.0/dialog/oauth';
    }

    protected function tokenEndpoint(): string
    {
        return self::GRAPH.'/oauth/access_token';
    }

    protected function scopes(): array
    {
        return ['instagram_basic', 'instagram_content_publish', 'pages_read_engagement'];
    }

    protected function mapAccount(array $tokenResponse): ConnectedAccount
    {
        $userToken = Arr::get($tokenResponse, 'access_token');

        $page = Arr::get(
            Http::get(self::GRAPH.'/me/accounts', [
                'fields'       => 'instagram_business_account,name,access_token',
                'access_token' => $userToken,
            ])->json(),
            'data.0',
            [],
        );

        $igId = Arr::get($page, 'instagram_business_account.id', '');

        return new ConnectedAccount(
            providerAccountId: (string) $igId,
            name: (string) Arr::get($page, 'name', 'Instagram Account'),
            accessToken: (string) Arr::get($page, 'access_token', $userToken),
            scopes: $this->scopes(),
            meta: ['ig_user_id' => $igId],
        );
    }

    public function publish(Channel $channel, PublishPayload $payload): PublishResult
    {
        $igId  = $channel->meta['ig_user_id'] ?? $channel->provider_account_id;
        $image = $payload->media[0]['url'] ?? null;

        if (! $image) {
            throw new PublishException('Instagram requires at least one image.', retryable: false);
        }

        // Step 1: create the media container.
        $container = Http::post(self::GRAPH."/{$igId}/media", [
            'image_url'    => $image,
            'caption'      => $payload->content,
            'access_token' => $channel->access_token,
        ]);

        if ($container->failed()) {
            throw new PublishException(
                "Instagram container creation failed: {$container->body()}",
                retryable: $container->serverError(),
            );
        }

        // Step 2: publish the container.
        $publish = Http::post(self::GRAPH."/{$igId}/media_publish", [
            'creation_id'  => Arr::get($container->json(), 'id'),
            'access_token' => $channel->access_token,
        ]);

        if ($publish->failed()) {
            throw new PublishException(
                "Instagram publish failed: {$publish->body()}",
                retryable: $publish->serverError(),
            );
        }

        return new PublishResult(
            providerPostId: (string) Arr::get($publish->json(), 'id', ''),
            raw: $publish->json() ?? [],
        );
    }
}
