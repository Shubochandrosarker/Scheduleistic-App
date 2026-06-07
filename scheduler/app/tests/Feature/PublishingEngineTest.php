<?php

namespace Tests\Feature;

use App\Jobs\PublishPostJob;
use App\Models\Channel;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\User;
use App\Models\Workspace;
use App\Social\Providers\FakeProvider;
use App\Social\ProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PublishingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Route every provider to the in-memory fake.
        $this->app->instance(ProviderManager::class, new ProviderManager(fake: true));
        FakeProvider::$shouldFail = false;
        FakeProvider::$failRetryable = true;
    }

    private function scheduledPost(Carbon $when): Post
    {
        $user = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $user->currentTeam->id, 'name' => 'Acme']);
        $channel = $workspace->channels()->create(['provider' => 'linkedin', 'name' => 'Acme LI']);

        $post = Post::create([
            'workspace_id' => $workspace->id,
            'status'       => Post::STATUS_SCHEDULED,
            'content'      => 'Hello',
            'scheduled_at' => $when,
        ]);
        $post->targets()->create(['channel_id' => $channel->id]);

        return $post;
    }

    public function test_due_posts_are_claimed_and_publish_jobs_dispatched(): void
    {
        Queue::fake();
        $post = $this->scheduledPost(Carbon::now()->subMinute());

        $this->artisan('posts:dispatch-due')->assertSuccessful();

        Queue::assertPushed(PublishPostJob::class, 1);
        $this->assertSame(Post::STATUS_PUBLISHING, $post->fresh()->status);
    }

    public function test_future_posts_are_not_dispatched(): void
    {
        Queue::fake();
        $post = $this->scheduledPost(Carbon::now()->addHour());

        $this->artisan('posts:dispatch-due')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame(Post::STATUS_SCHEDULED, $post->fresh()->status);
    }

    public function test_publish_job_publishes_a_target_and_marks_the_post_published(): void
    {
        $post = $this->scheduledPost(Carbon::now()->subMinute());
        $target = $post->targets->first();

        (new PublishPostJob($target))->handle(app(ProviderManager::class));

        $target->refresh();
        $this->assertSame(PostTarget::STATUS_PUBLISHED, $target->status);
        $this->assertNotEmpty($target->provider_post_id);
        $this->assertSame(Post::STATUS_PUBLISHED, $post->fresh()->status);
    }

    public function test_non_retryable_failure_marks_the_target_and_post_failed(): void
    {
        FakeProvider::$shouldFail = true;
        FakeProvider::$failRetryable = false;

        $post = $this->scheduledPost(Carbon::now()->subMinute());
        $target = $post->targets->first();

        (new PublishPostJob($target))->handle(app(ProviderManager::class));

        $target->refresh();
        $this->assertSame(PostTarget::STATUS_FAILED, $target->status);
        $this->assertNotEmpty($target->error);
        $this->assertSame(Post::STATUS_FAILED, $post->fresh()->status);
    }

    public function test_partial_failure_is_reflected_in_post_status(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $user->currentTeam->id, 'name' => 'Acme']);
        $ok   = $workspace->channels()->create(['provider' => 'linkedin', 'name' => 'LI']);
        $bad  = $workspace->channels()->create(['provider' => 'facebook', 'name' => 'FB']);

        $post = Post::create([
            'workspace_id' => $workspace->id,
            'status'       => Post::STATUS_PUBLISHING,
            'content'      => 'Hi',
        ]);
        $okTarget  = $post->targets()->create(['channel_id' => $ok->id]);
        $badTarget = $post->targets()->create(['channel_id' => $bad->id]);

        // First target succeeds.
        FakeProvider::$shouldFail = false;
        (new PublishPostJob($okTarget))->handle(app(ProviderManager::class));

        // Second target fails non-retryably.
        FakeProvider::$shouldFail = true;
        FakeProvider::$failRetryable = false;
        (new PublishPostJob($badTarget))->handle(app(ProviderManager::class));

        $this->assertSame(Post::STATUS_PARTIALLY_FAILED, $post->fresh()->status);
    }
}
