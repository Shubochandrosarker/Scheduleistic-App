<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Social\Data\PublishPayload;
use App\Social\Exceptions\PublishException;
use App\Social\Providers\ThreadsProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ThreadsProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.threads.client_id' => 'app-id', 'services.threads.client_secret' => 'app-secret']);
    }

    public function test_connecting_exchanges_for_a_long_lived_token_via_threads_own_grant(): void
    {
        Http::fake([
            // The standard authorization_code exchange every OAuth2 driver
            // does first (AbstractOAuthProvider::exchangeCode()).
            'graph.threads.net/v1.0/oauth/access_token' => Http::response([
                'access_token' => 'short-lived-token', 'user_id' => 'user-1',
            ]),
            // Threads' own long-lived-token endpoint, invoked from mapAccount().
            'graph.threads.net/access_token*' => Http::response([
                'access_token' => 'long-lived-token', 'expires_in' => 5_184_000,
            ]),
        ]);

        $account = (new ThreadsProvider)->exchangeCode('auth-code', 'https://app.test/callback');

        $this->assertSame('long-lived-token', $account->accessToken);
        $this->assertNotNull($account->expiresAt);

        // Threads' own grant type, distinct from Meta's fb_exchange_token —
        // and it needs no client_id, only the app secret and the token.
        Http::assertSent(function ($request) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['grant_type'] ?? null) === 'th_exchange_token' && ! isset($query['client_id']);
        });
    }

    public function test_a_failed_long_lived_exchange_falls_back_to_the_short_lived_token_rather_than_blocking_connect(): void
    {
        Http::fake([
            'graph.threads.net/v1.0/oauth/access_token' => Http::response([
                'access_token' => 'short-lived-token', 'user_id' => 'user-1',
            ]),
            'graph.threads.net/access_token*' => Http::response('bad request', 400),
        ]);

        $account = (new ThreadsProvider)->exchangeCode('auth-code', 'https://app.test/callback');

        $this->assertSame('short-lived-token', $account->accessToken);
        $this->assertNull($account->expiresAt);
    }

    public function test_publish_sends_text_only_when_there_is_no_media(): void
    {
        Http::fake([
            'graph.threads.net/v1.0/user-1/threads' => Http::response(['id' => 'container-1']),
            'graph.threads.net/v1.0/user-1/threads_publish' => Http::response(['id' => 'thread-1']),
        ]);

        $channel = new Channel(['provider' => 'threads', 'access_token' => 'tok', 'meta' => ['threads_user_id' => 'user-1']]);

        $result = (new ThreadsProvider)->publish($channel, new PublishPayload('Hello Threads'));

        $this->assertSame('thread-1', $result->providerPostId);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'user-1/threads')
            && ! str_contains($request->url(), 'publish')
            && $request['media_type'] === 'TEXT'
            && $request['text'] === 'Hello Threads');
    }

    public function test_publish_sends_an_image_container_when_media_is_present(): void
    {
        Http::fake([
            'graph.threads.net/v1.0/user-1/threads' => Http::response(['id' => 'container-1']),
            'graph.threads.net/v1.0/user-1/threads_publish' => Http::response(['id' => 'thread-1']),
        ]);

        $channel = new Channel(['provider' => 'threads', 'access_token' => 'tok', 'meta' => ['threads_user_id' => 'user-1']]);

        (new ThreadsProvider)->publish(
            $channel,
            new PublishPayload('Look at this', [['url' => 'https://cdn.test/photo.jpg']]),
        );

        Http::assertSent(fn ($request) => str_contains($request->url(), 'user-1/threads')
            && ! str_contains($request->url(), 'publish')
            && $request['media_type'] === 'IMAGE'
            && $request['image_url'] === 'https://cdn.test/photo.jpg');
    }

    public function test_publish_sends_a_video_container_when_media_is_a_video(): void
    {
        Http::fake([
            'graph.threads.net/v1.0/user-1/threads' => Http::response(['id' => 'container-1']),
            'graph.threads.net/v1.0/user-1/threads_publish' => Http::response(['id' => 'thread-1']),
        ]);

        $channel = new Channel(['provider' => 'threads', 'access_token' => 'tok', 'meta' => ['threads_user_id' => 'user-1']]);

        (new ThreadsProvider)->publish(
            $channel,
            new PublishPayload('Watch', [['url' => 'https://cdn.test/clip.mp4']]),
        );

        Http::assertSent(fn ($request) => str_contains($request->url(), 'user-1/threads')
            && ! str_contains($request->url(), 'publish')
            && $request['media_type'] === 'VIDEO'
            && $request['video_url'] === 'https://cdn.test/clip.mp4');
    }

    public function test_publish_throws_when_the_container_step_fails(): void
    {
        Http::fake(['graph.threads.net/*' => Http::response('server error', 500)]);

        $channel = new Channel(['provider' => 'threads', 'access_token' => 'tok', 'meta' => ['threads_user_id' => 'user-1']]);

        try {
            (new ThreadsProvider)->publish($channel, new PublishPayload('Hi'));
            $this->fail('Expected PublishException');
        } catch (PublishException $e) {
            $this->assertTrue($e->retryable); // 500 is retryable
        }
    }
}
