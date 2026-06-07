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
 * Facebook Page publishing via the Graph API. The page id + page access token
 * are resolved during the connect flow and stored on the channel.
 */
class FacebookProvider extends AbstractOAuthProvider
{
    protected const GRAPH = 'https://graph.facebook.com/v21.0';

    public function key(): string
    {
        return 'facebook';
    }

    public function label(): string
    {
        return 'Facebook Page';
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
        return ['pages_manage_posts', 'pages_read_engagement', 'business_management'];
    }

    protected function mapAccount(array $tokenResponse): ConnectedAccount
    {
        $userToken = Arr::get($tokenResponse, 'access_token');

        // Pick the first managed page; the UI lets the user choose in Phase 2.
        $page = Arr::get(
            Http::get(self::GRAPH.'/me/accounts', ['access_token' => $userToken])->json(),
            'data.0',
            [],
        );

        return new ConnectedAccount(
            providerAccountId: (string) Arr::get($page, 'id', ''),
            name: (string) Arr::get($page, 'name', 'Facebook Page'),
            accessToken: (string) Arr::get($page, 'access_token', $userToken),
            scopes: $this->scopes(),
            meta: ['page_id' => Arr::get($page, 'id')],
        );
    }

    public function publish(Channel $channel, PublishPayload $payload): PublishResult
    {
        $pageId = $channel->meta['page_id'] ?? $channel->provider_account_id;

        $response = Http::post(self::GRAPH."/{$pageId}/feed", [
            'message'      => $payload->content,
            'access_token' => $channel->access_token,
        ]);

        if ($response->failed()) {
            throw new PublishException(
                "Facebook publish failed: {$response->status()} {$response->body()}",
                retryable: $response->status() === 429 || $response->serverError(),
            );
        }

        return new PublishResult(
            providerPostId: (string) Arr::get($response->json(), 'id', ''),
            raw: $response->json() ?? [],
        );
    }
}
