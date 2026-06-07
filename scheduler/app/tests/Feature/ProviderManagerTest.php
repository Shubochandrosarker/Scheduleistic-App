<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Social\Data\PublishPayload;
use App\Social\Exceptions\PublishException;
use App\Social\Providers\FacebookProvider;
use App\Social\Providers\FakeProvider;
use App\Social\Providers\LinkedInProvider;
use App\Social\ProviderManager;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderManagerTest extends TestCase
{
    public function test_it_resolves_concrete_drivers_by_key(): void
    {
        $manager = new ProviderManager(fake: false);

        $this->assertInstanceOf(LinkedInProvider::class, $manager->driver('linkedin'));
        $this->assertInstanceOf(FacebookProvider::class, $manager->driver('facebook'));
        $this->assertContains('instagram', $manager->keys());
    }

    public function test_fake_mode_routes_every_key_to_the_fake_provider(): void
    {
        $manager = new ProviderManager(fake: true);

        $this->assertInstanceOf(FakeProvider::class, $manager->driver('linkedin'));
        $this->assertInstanceOf(FakeProvider::class, $manager->driver('anything'));
    }

    public function test_unknown_provider_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ProviderManager(fake: false))->driver('myspace');
    }

    public function test_linkedin_driver_publishes_via_http(): void
    {
        config(['services.linkedin.client_id' => 'id', 'services.linkedin.client_secret' => 'secret']);

        Http::fake([
            'api.linkedin.com/v2/ugcPosts' => Http::response([], 201, ['x-restli-id' => 'urn:li:share:123']),
        ]);

        $channel = new Channel([
            'provider'            => 'linkedin',
            'provider_account_id' => 'abc',
            'name'                => 'Tester',
            'access_token'        => 'token',
        ]);

        $result = (new LinkedInProvider())->publish($channel, new PublishPayload('Hello LinkedIn'));

        $this->assertSame('urn:li:share:123', $result->providerPostId);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'ugcPosts')
            && $request['author'] === 'urn:li:person:abc');
    }

    public function test_linkedin_driver_throws_publish_exception_on_failure(): void
    {
        Http::fake([
            'api.linkedin.com/v2/ugcPosts' => Http::response('rate limited', 429),
        ]);

        $channel = new Channel([
            'provider'            => 'linkedin',
            'provider_account_id' => 'abc',
            'name'                => 'Tester',
            'access_token'        => 'token',
        ]);

        try {
            (new LinkedInProvider())->publish($channel, new PublishPayload('Hi'));
            $this->fail('Expected PublishException');
        } catch (PublishException $e) {
            $this->assertTrue($e->retryable); // 429 is retryable
        }
    }
}
