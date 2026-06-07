<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Services\RecurrenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecurrenceTest extends TestCase
{
    use RefreshDatabase;

    private function recurringPost(string $rule): Post
    {
        $user = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $user->currentTeam->id, 'name' => 'Acme']);
        $channel = $workspace->channels()->create(['provider' => 'linkedin', 'name' => 'LI']);

        $post = Post::create([
            'workspace_id'   => $workspace->id,
            'status'         => Post::STATUS_PUBLISHED,
            'content'        => 'Weekly tip',
            'scheduled_at'   => Carbon::parse('2026-06-08 09:00:00'),
            'recurring_rule' => $rule,
        ]);
        $post->versions()->create(['provider' => 'linkedin', 'content' => 'LI tip']);
        $post->targets()->create(['channel_id' => $channel->id]);

        return $post;
    }

    public function test_it_creates_the_next_weekly_occurrence_with_cloned_versions_and_targets(): void
    {
        $post = $this->recurringPost('weekly');

        $next = app(RecurrenceService::class)->createNextOccurrence($post);

        $this->assertNotNull($next);
        $this->assertSame('2026-06-15 09:00:00', $next->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame(Post::STATUS_SCHEDULED, $next->status);
        $this->assertSame($post->id, $next->parent_post_id);
        $this->assertCount(1, $next->versions);
        $this->assertCount(1, $next->targets);
    }

    public function test_it_is_idempotent_for_the_same_slot(): void
    {
        $post = $this->recurringPost('daily');

        $first  = app(RecurrenceService::class)->createNextOccurrence($post);
        $second = app(RecurrenceService::class)->createNextOccurrence($post);

        $this->assertNotNull($first);
        $this->assertNull($second); // already created for that slot
        $this->assertDatabaseCount('posts', 2);
    }

    public function test_non_recurring_post_creates_nothing(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $user->currentTeam->id, 'name' => 'Acme']);
        $post = Post::create([
            'workspace_id' => $workspace->id,
            'status'       => Post::STATUS_PUBLISHED,
            'content'      => 'one-off',
            'scheduled_at' => now(),
        ]);

        $this->assertNull(app(RecurrenceService::class)->createNextOccurrence($post));
    }
}
