<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Social\Data\PublishPayload;
use App\Social\Exceptions\PublishException;
use App\Social\Providers\WordPressProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * WordPress posts directly to a tenant-supplied site URL on every publish —
 * unlike RSS feed ingestion and outbound webhooks, it never went through
 * SsrfGuard, despite the app already having and using one for exactly this
 * class of request.
 */
class WordPressProviderTest extends TestCase
{
    public function test_publish_rejects_a_site_url_that_resolves_to_a_private_address(): void
    {
        Http::fake(); // any request reaching this point is itself the bug

        $channel = new Channel([
            'provider' => 'wordpress',
            'access_token' => 'app-password',
            'meta' => ['site_url' => 'http://169.254.169.254', 'username' => 'admin'],
        ]);

        try {
            app(WordPressProvider::class)->publish($channel, new PublishPayload('Hello'));
            $this->fail('Expected PublishException');
        } catch (PublishException $e) {
            $this->assertStringContainsString('blocked address', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_publish_succeeds_against_a_public_site_url(): void
    {
        // A literal public IP, not a hostname: isFetchable() validates a
        // literal IP directly, so this doesn't depend on real DNS
        // resolution succeeding inside the test environment.
        Http::fake(['8.8.8.8/*' => Http::response(['id' => 42])]);

        $channel = new Channel([
            'provider' => 'wordpress',
            'access_token' => 'app-password',
            'meta' => ['site_url' => 'https://8.8.8.8', 'username' => 'admin'],
        ]);

        $result = app(WordPressProvider::class)->publish($channel, new PublishPayload('Hello WordPress'));

        $this->assertSame('42', $result->providerPostId);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'wp-json/wp/v2/posts'));
    }
}
