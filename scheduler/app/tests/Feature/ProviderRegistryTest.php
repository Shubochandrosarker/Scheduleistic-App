<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Social\Data\PublishPayload;
use App\Social\ProviderManager;
use App\Social\Providers\BlueskyProvider;
use App\Social\Providers\MastodonProvider;
use App\Social\Providers\PinterestProvider;
use App\Social\Providers\TikTokProvider;
use App\Social\Providers\WordPressProvider;
use App\Social\Providers\YouTubeProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderRegistryTest extends TestCase
{
    public function test_all_phase4_networks_are_registered(): void
    {
        $manager = new ProviderManager(fake: false);
        $keys = $manager->keys();

        foreach (['pinterest', 'threads', 'tiktok', 'youtube', 'mastodon', 'bluesky', 'medium', 'wordpress'] as $key) {
            $this->assertContains($key, $keys, "Missing provider: {$key}");
        }

        $this->assertInstanceOf(PinterestProvider::class, $manager->driver('pinterest'));
        $this->assertInstanceOf(TikTokProvider::class, $manager->driver('tiktok'));
        $this->assertInstanceOf(YouTubeProvider::class, $manager->driver('youtube'));
        $this->assertInstanceOf(MastodonProvider::class, $manager->driver('mastodon'));
        $this->assertInstanceOf(BlueskyProvider::class, $manager->driver('bluesky'));
        $this->assertInstanceOf(WordPressProvider::class, $manager->driver('wordpress'));
    }

    public function test_pinterest_driver_publishes_a_pin(): void
    {
        Http::fake(['api.pinterest.com/*' => Http::response(['id' => 'pin-123'], 201)]);

        $channel = new Channel([
            'provider'     => 'pinterest',
            'name'         => 'Board',
            'access_token' => 'token',
            'meta'         => ['board_id' => 'b1'],
        ]);

        $result = (new PinterestProvider())->publish(
            $channel,
            new PublishPayload('A pin', [['url' => 'https://img/x.jpg']]),
        );

        $this->assertSame('pin-123', $result->providerPostId);
    }

    public function test_mastodon_driver_publishes_and_reads_metrics(): void
    {
        Http::fake([
            'mastodon.social/api/v1/statuses' => Http::response(['id' => '99', 'favourites_count' => 5, 'reblogs_count' => 2, 'replies_count' => 1]),
            'mastodon.social/api/v1/statuses/*' => Http::response(['favourites_count' => 5, 'reblogs_count' => 2, 'replies_count' => 1]),
        ]);

        $channel = new Channel([
            'provider'     => 'mastodon',
            'name'         => 'Masto',
            'access_token' => 'token',
            'meta'         => ['instance' => 'https://mastodon.social'],
        ]);

        $driver = new MastodonProvider();
        $result = $driver->publish($channel, new PublishPayload('toot'));
        $this->assertSame('99', $result->providerPostId);

        $metrics = $driver->fetchMetrics($channel, '99');
        $this->assertSame(5, $metrics['likes']);
        $this->assertSame(2, $metrics['shares']);
    }
}
