<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Social\Data\PublishPayload;
use App\Social\Exceptions\PublishException;
use App\Social\Providers\FacebookProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Facebook, Instagram, and Threads were named directly in the platform-
 * activation request and had zero test coverage of their driver code
 * before this — the generic connect/callback plumbing was tested via
 * FakeProvider, which proves nothing about any specific network's real
 * API-calling code.
 */
class FacebookProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.facebook.client_id' => 'app-id', 'services.facebook.client_secret' => 'app-secret']);
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

    public function test_connecting_exchanges_for_a_long_lived_token_before_listing_pages(): void
    {
        $this->fakeSuccessfulConnect([
            ['id' => 'page-1', 'name' => 'Acme Page', 'access_token' => 'page-1-token'],
        ]);

        $account = (new FacebookProvider)->exchangeCode('auth-code', 'https://app.test/callback');

        $this->assertSame('page-1', $account->providerAccountId);
        $this->assertSame('Acme Page', $account->name);
        $this->assertSame('page-1-token', $account->accessToken);
        $this->assertNotNull($account->expiresAt);

        // The user token used to list pages must be the long-lived one, not
        // the short-lived one the initial code exchange returned.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/me/accounts')
            && $request['access_token'] === 'long-lived-user-token');
    }

    public function test_the_first_page_is_selected_and_every_page_is_recorded_for_visibility(): void
    {
        $this->fakeSuccessfulConnect([
            ['id' => 'page-1', 'name' => 'First Page', 'access_token' => 'token-1'],
            ['id' => 'page-2', 'name' => 'Second Page', 'access_token' => 'token-2'],
        ]);

        $account = (new FacebookProvider)->exchangeCode('auth-code', 'https://app.test/callback');

        $this->assertSame('page-1', $account->providerAccountId);
        $this->assertCount(2, $account->meta['available_accounts']);
        $this->assertSame('page-2', $account->meta['available_accounts'][1]['id']);

        // Never a second page's own access token — only id/name.
        $this->assertArrayNotHasKey('access_token', $account->meta['available_accounts'][1]);
    }

    public function test_connecting_an_account_with_no_pages_fails_clearly_instead_of_creating_a_broken_channel(): void
    {
        $this->fakeSuccessfulConnect([]);

        $this->expectException(PublishException::class);
        $this->expectExceptionMessageMatches('/No Facebook Pages/');

        (new FacebookProvider)->exchangeCode('auth-code', 'https://app.test/callback');
    }

    public function test_publish_posts_text_only_to_the_feed_when_there_is_no_media(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'post-1'])]);

        $channel = new Channel(['provider' => 'facebook', 'access_token' => 'tok', 'meta' => ['page_id' => 'page-1']]);

        $result = (new FacebookProvider)->publish($channel, new PublishPayload('Hello Facebook'));

        $this->assertSame('post-1', $result->providerPostId);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'page-1/feed') && $request['message'] === 'Hello Facebook');
    }

    public function test_publish_posts_a_photo_when_the_media_is_an_image(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'photo-1', 'post_id' => 'page-1_post-1'])]);

        $channel = new Channel(['provider' => 'facebook', 'access_token' => 'tok', 'meta' => ['page_id' => 'page-1']]);

        $result = (new FacebookProvider)->publish(
            $channel,
            new PublishPayload('Caption', [['url' => 'https://cdn.test/photo.jpg']]),
        );

        // Prefers the wrapping feed post id over the photo object's own id.
        $this->assertSame('page-1_post-1', $result->providerPostId);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'page-1/photos')
            && $request['url'] === 'https://cdn.test/photo.jpg'
            && $request['caption'] === 'Caption');
    }

    public function test_publish_posts_a_video_when_the_media_is_a_video(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'video-1'])]);

        $channel = new Channel(['provider' => 'facebook', 'access_token' => 'tok', 'meta' => ['page_id' => 'page-1']]);

        (new FacebookProvider)->publish(
            $channel,
            new PublishPayload('Watch this', [['url' => 'https://cdn.test/clip.mp4']]),
        );

        Http::assertSent(fn ($request) => str_contains($request->url(), 'page-1/videos')
            && $request['file_url'] === 'https://cdn.test/clip.mp4'
            && $request['description'] === 'Watch this');
    }

    public function test_publish_throws_a_retryable_exception_on_rate_limit(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response('rate limited', 429)]);

        $channel = new Channel(['provider' => 'facebook', 'access_token' => 'tok', 'meta' => ['page_id' => 'page-1']]);

        try {
            (new FacebookProvider)->publish($channel, new PublishPayload('Hi'));
            $this->fail('Expected PublishException');
        } catch (PublishException $e) {
            $this->assertTrue($e->retryable);
        }
    }
}
