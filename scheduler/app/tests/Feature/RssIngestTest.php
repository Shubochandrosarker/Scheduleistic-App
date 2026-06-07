<?php

namespace Tests\Feature;

use App\Models\Feed;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Services\RssIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RssIngestTest extends TestCase
{
    use RefreshDatabase;

    private const RSS = <<<'XML'
<?xml version="1.0"?>
<rss version="2.0"><channel>
  <title>Blog</title>
  <item><title>First article</title><link>https://blog.test/1</link><guid>g1</guid></item>
  <item><title>Second article</title><link>https://blog.test/2</link><guid>g2</guid></item>
</channel></rss>
XML;

    private function feedWithChannel(): Feed
    {
        $user = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $user->currentTeam->id, 'name' => 'Acme']);
        $workspace->channels()->create(['provider' => 'linkedin', 'name' => 'LI']);

        return $workspace->feeds()->create(['url' => 'https://blog.test/feed']);
    }

    public function test_it_parses_rss_items(): void
    {
        $items = app(RssIngestService::class)->parse(self::RSS);

        $this->assertCount(2, $items);
        $this->assertSame('First article', $items[0]['title']);
        $this->assertSame('g1', $items[0]['guid']);
    }

    public function test_ingest_creates_draft_posts_and_dedupes(): void
    {
        $feed = $this->feedWithChannel();
        $service = app(RssIngestService::class);

        $created = $service->ingest($feed, self::RSS);
        $this->assertSame(2, $created);
        $this->assertSame(2, Post::where('workspace_id', $feed->workspace_id)->count());
        $this->assertSame(Post::STATUS_DRAFT, Post::first()->status);

        // Re-ingesting the same feed creates nothing new (guids already seen).
        $createdAgain = $service->ingest($feed->fresh(), self::RSS);
        $this->assertSame(0, $createdAgain);
        $this->assertSame(2, Post::count());
    }
}
