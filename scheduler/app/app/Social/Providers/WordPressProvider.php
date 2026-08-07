<?php

namespace App\Social\Providers;

use App\Models\Channel;
use App\Social\Data\ConnectedAccount;
use App\Social\Data\PublishPayload;
use App\Social\Data\PublishResult;
use App\Social\Exceptions\PublishException;
use App\Support\SsrfGuard;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * WordPress publishing via the REST API using an application password.
 * The channel meta holds the site URL and username; access_token is the app
 * password. This is the outbound side of the WORDPRESSISTIC integration.
 */
class WordPressProvider extends AbstractTokenProvider
{
    public function __construct(private readonly SsrfGuard $ssrf) {}

    public function key(): string
    {
        return 'wordpress';
    }

    public function label(): string
    {
        return 'WordPress';
    }

    public function fields(): array
    {
        return [
            ['key' => 'site_url', 'label' => 'Site URL (e.g. https://blog.example.com)', 'type' => 'url'],
            ['key' => 'username', 'label' => 'Username', 'type' => 'text'],
            ['key' => 'app_password', 'label' => 'Application password', 'type' => 'password'],
        ];
    }

    public function accountFromCredentials(array $input): ConnectedAccount
    {
        $site = rtrim($input['site_url'] ?? '', '/');

        return new ConnectedAccount(
            providerAccountId: 'wordpress-'.substr(sha1($site.($input['username'] ?? '')), 0, 8),
            name: 'WordPress ('.parse_url($site, PHP_URL_HOST).')',
            accessToken: $input['app_password'] ?? '',
            scopes: [],
            meta: ['site_url' => $site, 'username' => $input['username'] ?? null],
        );
    }

    public function publish(Channel $channel, PublishPayload $payload): PublishResult
    {
        $site = rtrim($channel->meta['site_url'] ?? '', '/');
        $user = $channel->meta['username'] ?? '';

        if (! $site || ! $user) {
            throw new PublishException('WordPress site URL and username are required.', retryable: false);
        }

        // The engine posts directly to a tenant-supplied URL on every
        // publish; re-checked here (not just at connect time) because DNS
        // can be re-pointed at a private address after the channel is
        // saved, and this worker runs inside the network perimeter — the
        // same guard RSS feed ingestion and outbound webhooks already use.
        if (! $this->ssrf->isFetchable($site)) {
            throw new PublishException('WordPress site URL resolves to a blocked address.', retryable: false);
        }

        $response = Http::withBasicAuth($user, $channel->access_token)
            ->post("{$site}/wp-json/wp/v2/posts", [
                'title' => mb_substr(strtok($payload->content, "\n"), 0, 120),
                'content' => $payload->content,
                'status' => 'publish',
            ]);

        if ($response->failed()) {
            throw new PublishException("WordPress publish failed: {$response->body()}", retryable: $response->serverError());
        }

        return new PublishResult((string) Arr::get($response->json(), 'id', ''), $response->json() ?? []);
    }
}
