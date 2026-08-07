<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Social\Data\PublishPayload;
use App\Social\Exceptions\PublishException;
use App\Social\Providers\InstagramProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstagramProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.instagram.client_id' => 'app-id', 'services.instagram.client_secret' => 'app-secret']);
    }

    protected function fakeSuccessfulConnect(array $pages): void
    {
        Http::fake([
            'graph.facebook.com/v21.0/oauth/access_token*' => Http::response([
                'access_token' => 'long-lived-user-token', 'expires_in' => 5_184_000,
            ]),
            'graph.facebook.com/v21.0/me/accounts*' => Http::response(['data' => $pages]),
        ]);
    }

    public function test_connecting_selects_the_first_page_with_a_linked_instagram_account(): void
    {
        $this->fakeSuccessfulConnect([
            // A page with no linked Instagram account must be skipped, not
            // silently chosen as if it could publish.
            ['id' => 'page-0', 'name' => 'No IG here', 'access_token' => 'tok-0'],
            [
                'id' => 'page-1', 'name' => 'Acme Page', 'access_token' => 'tok-1',
                'instagram_business_account' => ['id' => 'ig-1', 'username' => 'acme'],
            ],
        ]);

        $account = (new InstagramProvider)->exchangeCode('auth-code', 'https://app.test/callback');

        $this->assertSame('ig-1', $account->providerAccountId);
        $this->assertSame('acme', $account->name);
        $this->assertSame('tok-1', $account->accessToken);
        $this->assertNotNull($account->expiresAt);
        $this->assertCount(1, $account->meta['available_accounts']);
    }

    public function test_connecting_an_account_with_no_linked_instagram_business_account_fails_clearly(): void
    {
        $this->fakeSuccessfulConnect([
            ['id' => 'page-1', 'name' => 'No IG', 'access_token' => 'tok-1'],
        ]);

        $this->expectException(PublishException::class);
        $this->expectExceptionMessageMatches('/No Instagram professional/');

        (new InstagramProvider)->exchangeCode('auth-code', 'https://app.test/callback');
    }

    public function test_publish_requires_an_image(): void
    {
        $channel = new Channel(['provider' => 'instagram', 'access_token' => 'tok', 'meta' => ['ig_user_id' => 'ig-1']]);

        $this->expectException(PublishException::class);

        (new InstagramProvider)->publish($channel, new PublishPayload('No image here'));
    }

    public function test_publish_creates_a_container_then_publishes_it(): void
    {
        Http::fake([
            'graph.facebook.com/v21.0/ig-1/media' => Http::response(['id' => 'container-1']),
            'graph.facebook.com/v21.0/ig-1/media_publish' => Http::response(['id' => 'media-1']),
        ]);

        $channel = new Channel(['provider' => 'instagram', 'access_token' => 'tok', 'meta' => ['ig_user_id' => 'ig-1']]);

        $result = (new InstagramProvider)->publish(
            $channel,
            new PublishPayload('Caption', [['url' => 'https://cdn.test/photo.jpg']]),
        );

        $this->assertSame('media-1', $result->providerPostId);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'ig-1/media')
            && ! str_contains($request->url(), 'publish')
            && $request['image_url'] === 'https://cdn.test/photo.jpg');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'media_publish')
            && $request['creation_id'] === 'container-1');
    }

    public function test_publish_throws_when_container_creation_fails(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response('bad request', 400)]);

        $channel = new Channel(['provider' => 'instagram', 'access_token' => 'tok', 'meta' => ['ig_user_id' => 'ig-1']]);

        $this->expectException(PublishException::class);

        (new InstagramProvider)->publish($channel, new PublishPayload('Caption', [['url' => 'https://cdn.test/photo.jpg']]));
    }
}
