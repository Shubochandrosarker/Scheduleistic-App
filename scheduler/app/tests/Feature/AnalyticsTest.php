<?php

namespace Tests\Feature;

use App\Jobs\FetchMetricsJob;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AnalyticsService;
use App\Social\ProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(ProviderManager::class, new ProviderManager(fake: true));
    }

    private function publishedTarget(): PostTarget
    {
        $user = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $user->currentTeam->id, 'name' => 'Acme']);
        $channel = $workspace->channels()->create(['provider' => 'linkedin', 'name' => 'LI']);
        $post = Post::create(['workspace_id' => $workspace->id, 'content' => 'hi', 'status' => Post::STATUS_PUBLISHED]);

        return $post->targets()->create([
            'channel_id'       => $channel->id,
            'status'           => PostTarget::STATUS_PUBLISHED,
            'provider_post_id' => 'p-1',
            'published_at'     => now(),
        ]);
    }

    public function test_capture_stores_metrics_from_the_provider(): void
    {
        $target = $this->publishedTarget();

        app(AnalyticsService::class)->capture($target);

        $this->assertDatabaseHas('analytics_metrics', [
            'post_target_id' => $target->id,
            'metric'         => 'likes',
            'value'          => 10,
        ]);
    }

    public function test_totals_for_team_aggregates_latest_metrics(): void
    {
        $target = $this->publishedTarget();
        $service = app(AnalyticsService::class);

        $service->capture($target);
        $service->capture($target); // a second capture; totals use the latest per target

        $team = $target->post->workspace->team;
        $totals = $service->totalsForTeam($team);

        $this->assertSame(10, $totals['likes']);
        $this->assertSame(100, $totals['impressions']);
    }

    public function test_fetch_command_queues_jobs_for_published_targets(): void
    {
        Queue::fake();
        $this->publishedTarget();

        $this->artisan('analytics:fetch')->assertSuccessful();

        Queue::assertPushed(FetchMetricsJob::class, 1);
    }
}
