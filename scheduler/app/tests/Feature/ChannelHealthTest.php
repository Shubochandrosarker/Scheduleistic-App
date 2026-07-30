<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\ChannelHealthEvent;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ChannelHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ChannelHealthTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->owner = User::factory()->withPersonalTeam()->create();
        $this->workspace = Workspace::create(['team_id' => $this->owner->currentTeam->id, 'name' => 'Brand']);
    }

    protected function channel(array $overrides = []): Channel
    {
        return $this->workspace->channels()->create(array_merge([
            'provider' => 'linkedin',
            'name'     => 'Company page',
        ], $overrides));
    }

    protected function health(): ChannelHealthService
    {
        return app(ChannelHealthService::class);
    }

    public function test_a_channel_starts_connected_and_may_publish(): void
    {
        $channel = $this->channel();

        $this->assertSame(ChannelHealthEvent::STATE_CONNECTED, $channel->health_state);
        $this->assertTrue($channel->canPublish());
    }

    public function test_an_expired_token_blocks_publishing(): void
    {
        $channel = $this->channel(['token_expires_at' => now()->subDay()]);

        $state = $this->health()->evaluate($channel);

        $this->assertSame(ChannelHealthEvent::STATE_TOKEN_EXPIRED, $state);
        $this->assertFalse($channel->fresh()->canPublish());
        $this->assertDatabaseHas('channel_health_events', [
            'channel_id' => $channel->id,
            'state'      => ChannelHealthEvent::STATE_TOKEN_EXPIRED,
            'severity'   => 'critical',
        ]);
    }

    public function test_a_token_expiring_soon_warns_but_still_publishes(): void
    {
        $channel = $this->channel(['token_expires_at' => now()->addDays(3)]);

        $state = $this->health()->evaluate($channel);

        $this->assertSame(ChannelHealthEvent::STATE_TOKEN_EXPIRING, $state);
        // Warning, not a block: the connection still works today.
        $this->assertTrue($channel->fresh()->canPublish());
    }

    public function test_a_healthy_long_lived_token_is_left_alone(): void
    {
        $channel = $this->channel(['token_expires_at' => now()->addMonths(6)]);

        $this->assertSame(ChannelHealthEvent::STATE_CONNECTED, $this->health()->evaluate($channel));
    }

    public function test_recording_the_same_problem_twice_does_not_duplicate_the_event(): void
    {
        $channel = $this->channel();

        $this->health()->record($channel, ChannelHealthEvent::STATE_RATE_LIMITED, 'Slow down');
        $this->health()->record($channel, ChannelHealthEvent::STATE_RATE_LIMITED, 'Still rate limited');

        $this->assertSame(1, ChannelHealthEvent::where('channel_id', $channel->id)->count());
        $this->assertSame('Still rate limited', ChannelHealthEvent::first()->message);
    }

    public function test_recovery_resolves_open_events_without_deleting_them(): void
    {
        $channel = $this->channel();

        $this->health()->record($channel, ChannelHealthEvent::STATE_PUBLISH_FAILED, 'Publish failed');
        $this->health()->recover($channel);

        $this->assertSame(ChannelHealthEvent::STATE_CONNECTED, $channel->fresh()->health_state);

        // The timeline survives — the row is resolved, not removed.
        $event = ChannelHealthEvent::first();
        $this->assertNotNull($event);
        $this->assertNotNull($event->resolved_at);
    }

    public function test_the_health_page_summarises_by_severity(): void
    {
        $this->channel(['name' => 'Healthy']);
        $this->health()->record($this->channel(['name' => 'Warning']), ChannelHealthEvent::STATE_RATE_LIMITED);
        $this->health()->record($this->channel(['name' => 'Blocked']), ChannelHealthEvent::STATE_TOKEN_EXPIRED);

        $props = $this->actingAs($this->owner)
            ->get(route('channels.health'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame(1, $props['summary']['healthy']);
        $this->assertSame(1, $props['summary']['warning']);
        $this->assertSame(1, $props['summary']['blocked']);
    }

    public function test_refreshing_re_evaluates_every_visible_channel(): void
    {
        $channel = $this->channel(['token_expires_at' => now()->subHour()]);

        $this->actingAs($this->owner)
            ->post(route('channels.health.refresh'))
            ->assertSessionHasNoErrors();

        $this->assertSame(ChannelHealthEvent::STATE_TOKEN_EXPIRED, $channel->fresh()->health_state);
    }

    // --- Integration with the publishing engine ---------------------------

    public function test_the_publish_job_refuses_to_use_a_blocked_channel(): void
    {
        config(['scheduleistic.fake_publishing' => true]);

        $channel = $this->channel(['token_expires_at' => now()->subDay()]);
        $this->health()->evaluate($channel);

        $post = Post::create([
            'workspace_id' => $this->workspace->id,
            'content'      => 'Hello',
            'status'       => Post::STATUS_SCHEDULED,
            'scheduled_at' => now(),
            'author_id'    => $this->owner->id,
        ]);

        $target = $post->targets()->create(['channel_id' => $channel->id, 'status' => PostTarget::STATUS_PENDING]);

        (new \App\Jobs\PublishPostJob($target))->handle(app(\App\Social\ProviderManager::class));

        $target->refresh();

        $this->assertSame(PostTarget::STATUS_FAILED, $target->status);
        $this->assertStringContainsString('reconnecting', $target->error);
    }

    public function test_a_publish_failure_is_recorded_against_the_channel(): void
    {
        $channel = $this->channel();

        $post = Post::create([
            'workspace_id' => $this->workspace->id,
            'content'      => 'Hello',
            'status'       => Post::STATUS_SCHEDULED,
            'scheduled_at' => now(),
            'author_id'    => $this->owner->id,
        ]);

        $target = $post->targets()->create(['channel_id' => $channel->id, 'status' => PostTarget::STATUS_PENDING]);

        (new \App\Jobs\PublishPostJob($target))->failed(new \RuntimeException('Provider said no'));

        $this->assertDatabaseHas('channel_health_events', [
            'channel_id' => $channel->id,
            'state'      => ChannelHealthEvent::STATE_PUBLISH_FAILED,
        ]);
    }

    public function test_a_successful_publish_stamps_the_channel_and_clears_a_prior_problem(): void
    {
        config(['scheduleistic.fake_publishing' => true]);

        $channel = $this->channel();
        $this->health()->record($channel, ChannelHealthEvent::STATE_PUBLISH_FAILED, 'Earlier failure');

        $post = Post::create([
            'workspace_id' => $this->workspace->id,
            'content'      => 'Hello',
            'status'       => Post::STATUS_SCHEDULED,
            'scheduled_at' => now(),
            'author_id'    => $this->owner->id,
        ]);

        $target = $post->targets()->create(['channel_id' => $channel->id, 'status' => PostTarget::STATUS_PENDING]);

        (new \App\Jobs\PublishPostJob($target))->handle(app(\App\Social\ProviderManager::class));

        $channel->refresh();

        $this->assertSame(PostTarget::STATUS_PUBLISHED, $target->fresh()->status);
        $this->assertNotNull($channel->last_published_at);
        $this->assertSame(ChannelHealthEvent::STATE_CONNECTED, $channel->health_state);
    }
}
