<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\User;
use App\Models\Workspace;
use App\Social\Data\PublishPayload;
use App\Social\Exceptions\PublishException;
use App\Social\Providers\TikTokProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * TikTok's v2 API names the app-identifying OAuth parameter `client_key`,
 * not `client_id` — the shape every other provider (and the shared base
 * class) uses. Every call that authenticates the app to TikTok must use the
 * right name or TikTok rejects it regardless of how correct the credential
 * values are.
 */
class TikTokProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tiktok.client_id' => 'app-key-123', 'services.tiktok.client_secret' => 'app-secret']);
    }

    protected function persistedChannel(array $attributes = []): Channel
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $owner->currentTeam->id, 'name' => 'Brand']);

        return $workspace->channels()->create(array_merge([
            'provider' => 'tiktok',
            'name' => 'TikTok',
            'access_token' => 'old-token',
        ], $attributes));
    }

    public function test_the_authorization_url_sends_client_key_not_client_id(): void
    {
        $url = (new TikTokProvider)->authorizationUrl('state-abc', 'https://app.test/callback');

        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('app-key-123', $query['client_key']);
        $this->assertArrayNotHasKey('client_id', $query);
    }

    public function test_code_exchange_sends_client_key_not_client_id(): void
    {
        Http::fake([
            'open.tiktokapis.com/*' => Http::response([
                'open_id' => 'user-1', 'access_token' => 'tok', 'refresh_token' => 'ref', 'expires_in' => 86400,
            ]),
        ]);

        $account = (new TikTokProvider)->exchangeCode('auth-code', 'https://app.test/callback');

        $this->assertSame('user-1', $account->providerAccountId);
        $this->assertNotNull($account->expiresAt);

        Http::assertSent(function ($request) {
            return $request['client_key'] === 'app-key-123'
                && ! isset($request['client_id']);
        });
    }

    public function test_refresh_sends_client_key_not_client_id_and_updates_the_channel(): void
    {
        Http::fake([
            'open.tiktokapis.com/*' => Http::response([
                'access_token' => 'new-token', 'refresh_token' => 'new-refresh', 'expires_in' => 3600,
            ]),
        ]);

        $channel = $this->persistedChannel(['refresh_token' => 'old-refresh']);

        (new TikTokProvider)->refreshToken($channel);

        Http::assertSent(fn ($request) => $request['client_key'] === 'app-key-123' && $request['grant_type'] === 'refresh_token');

        $channel->refresh();
        $this->assertSame('new-token', $channel->access_token);
        $this->assertSame('connected', $channel->status);
    }

    public function test_a_failed_refresh_marks_the_channel_expired(): void
    {
        Http::fake(['open.tiktokapis.com/*' => Http::response('invalid_grant', 400)]);

        $channel = $this->persistedChannel(['refresh_token' => 'old-refresh']);

        (new TikTokProvider)->refreshToken($channel);

        $this->assertSame('expired', $channel->fresh()->status);
    }

    public function test_publish_still_requires_a_video_url(): void
    {
        $channel = new Channel(['provider' => 'tiktok', 'access_token' => 'tok']);

        $this->expectException(PublishException::class);

        (new TikTokProvider)->publish($channel, new PublishPayload('No video here'));
    }

    public function test_publish_sends_the_video_to_tiktok(): void
    {
        Http::fake(['open.tiktokapis.com/*' => Http::response(['data' => ['publish_id' => 'pub-1']])]);

        $channel = new Channel(['provider' => 'tiktok', 'access_token' => 'tok']);

        $result = (new TikTokProvider)->publish(
            $channel,
            new PublishPayload('Caption', [['url' => 'https://cdn.test/video.mp4']]),
        );

        $this->assertSame('pub-1', $result->providerPostId);
    }
}
