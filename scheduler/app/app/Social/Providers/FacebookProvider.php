<?php

namespace App\Social\Providers;

use App\Models\Channel;
use App\Social\Data\ConnectedAccount;
use App\Social\Data\PublishPayload;
use App\Social\Data\PublishResult;
use App\Social\Exceptions\PublishException;
use App\Social\Providers\Concerns\DetectsMediaKind;
use App\Social\Providers\Concerns\ExchangesMetaLongLivedToken;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * Facebook Page publishing via the Graph API. The page id + page access token
 * are resolved during the connect flow and stored on the channel.
 */
class FacebookProvider extends AbstractOAuthProvider
{
    use DetectsMediaKind;
    use ExchangesMetaLongLivedToken;

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
        // Exchanging for a long-lived user token before deriving the Page
        // token matters beyond the user token's own lifetime: a Page token
        // derived from a long-lived user token is itself effectively
        // non-expiring, one derived from a short-lived token is not.
        $exchanged = $this->exchangeForLongLivedToken((string) Arr::get($tokenResponse, 'access_token'));
        $userToken = $exchanged['token'];

        $pages = (array) Arr::get(
            Http::get(self::GRAPH.'/me/accounts', [
                'fields' => 'id,name,access_token',
                'access_token' => $userToken,
            ])->json(),
            'data',
            [],
        );

        if ($pages === []) {
            throw new PublishException(
                'No Facebook Pages found for this account. You must be an admin of at least '
                    .'one Facebook Page to connect it — personal profiles cannot be connected directly.',
                retryable: false,
            );
        }

        // The first page is connected automatically so the common
        // single-page case needs no extra step; every other page the user
        // administers is still recorded (name/id only — never a second
        // page's own token) so at least the choice is visible instead of
        // silent, per the audit's finding on this driver.
        $page = $pages[0];

        return new ConnectedAccount(
            providerAccountId: (string) Arr::get($page, 'id', ''),
            name: (string) Arr::get($page, 'name', 'Facebook Page'),
            accessToken: (string) Arr::get($page, 'access_token', $userToken),
            expiresAt: $exchanged['expiresAt'],
            scopes: $this->scopes(),
            meta: [
                'page_id' => Arr::get($page, 'id'),
                'available_accounts' => collect($pages)
                    ->map(fn ($p) => ['id' => Arr::get($p, 'id'), 'name' => Arr::get($p, 'name')])
                    ->values()
                    ->all(),
            ],
        );
    }

    public function publish(Channel $channel, PublishPayload $payload): PublishResult
    {
        $pageId = $channel->meta['page_id'] ?? $channel->provider_account_id;
        $media = $payload->media[0] ?? null;

        [$endpoint, $body] = match (true) {
            $media === null => [
                "{$pageId}/feed",
                ['message' => $payload->content],
            ],
            $this->mediaKind($media) === 'video' => [
                "{$pageId}/videos",
                ['file_url' => $media['url'], 'description' => $payload->content],
            ],
            default => [
                "{$pageId}/photos",
                ['url' => $media['url'], 'caption' => $payload->content],
            ],
        };

        $response = Http::post(self::GRAPH."/{$endpoint}", [...$body, 'access_token' => $channel->access_token]);

        if ($response->failed()) {
            throw new PublishException(
                "Facebook publish failed: {$response->status()} {$response->body()}",
                retryable: $response->status() === 429 || $response->serverError(),
            );
        }

        // A photo/video upload response carries both the media object's own
        // `id` and the resulting feed post's `post_id` — the latter is what
        // every other consumer (metrics lookup, a "view post" link) means
        // by "the post," so it's preferred when present. A plain /feed text
        // post only ever returns `id`.
        return new PublishResult(
            providerPostId: (string) (Arr::get($response->json(), 'post_id') ?: Arr::get($response->json(), 'id', '')),
            raw: $response->json() ?? [],
        );
    }
}
