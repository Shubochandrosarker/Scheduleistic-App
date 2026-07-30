<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\ContentPillar;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PlannerTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->owner = User::factory()->withPersonalTeam()->create();
        $this->owner->currentTeam->update(['plan' => 'scale']);
        $this->workspace = Workspace::create(['team_id' => $this->owner->currentTeam->id, 'name' => 'Brand']);
    }

    protected function makePost(array $attributes = []): Post
    {
        return Post::create(array_merge([
            'workspace_id' => $this->workspace->id,
            'content'      => 'A scheduled post',
            'status'       => Post::STATUS_SCHEDULED,
            'scheduled_at' => now()->addDay(),
            'timezone'     => 'UTC',
        ], $attributes));
    }

    protected function props($response): array
    {
        return $response->viewData('page')['props'];
    }

    // --- Views ------------------------------------------------------------

    public function test_the_planner_renders_with_filter_options(): void
    {
        $this->makePost();

        $props = $this->props($this->actingAs($this->owner)->get(route('planner.index'))->assertOk());

        $this->assertSame('Planner/Index', $this->actingAs($this->owner)->get(route('planner.index'))->viewData('page')['component']);
        $this->assertSame('month', $props['view']);
        $this->assertArrayHasKey('workspaces', $props['options']);
        $this->assertArrayHasKey('statuses', $props['options']);
    }

    public function test_each_view_derives_its_own_date_window(): void
    {
        $anchor = '2026-06-15';

        $week = $this->props($this->actingAs($this->owner)->get(route('planner.index', ['view' => 'week', 'from' => $anchor])));
        $month = $this->props($this->actingAs($this->owner)->get(route('planner.index', ['view' => 'month', 'from' => $anchor])));

        $weekSpan = strtotime($week['window']['to']) - strtotime($week['window']['from']);
        $monthSpan = strtotime($month['window']['to']) - strtotime($month['window']['from']);

        // The week view must load strictly less than the month view; that is
        // the whole point of windowing server-side.
        $this->assertLessThan($monthSpan, $weekSpan);
        $this->assertLessThanOrEqual(7 * 86400, $weekSpan);
    }

    public function test_an_invalid_view_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->get(route('planner.index', ['view' => 'wall-chart']))
            ->assertSessionHasErrors('view');
    }

    public function test_drafts_without_a_date_are_always_included(): void
    {
        $draft = $this->makePost(['status' => Post::STATUS_DRAFT, 'scheduled_at' => null]);

        // Anchor the window a year away — the draft must still come back,
        // otherwise it would be invisible on every view.
        $props = $this->props($this->actingAs($this->owner)->get(route('planner.index', ['from' => now()->addYear()->toDateString()])));

        $this->assertContains($draft->id, collect($props['posts'])->pluck('id')->all());
    }

    // --- Filters ----------------------------------------------------------

    public function test_posts_can_be_filtered_by_campaign_pillar_tag_and_status(): void
    {
        $campaign = Campaign::create(['workspace_id' => $this->workspace->id, 'name' => 'Launch']);
        $pillar = ContentPillar::create(['workspace_id' => $this->workspace->id, 'name' => 'Education']);
        $tag = Tag::create(['workspace_id' => $this->workspace->id, 'name' => 'evergreen']);

        $match = $this->makePost(['campaign_id' => $campaign->id, 'content_pillar_id' => $pillar->id]);
        $match->tags()->attach($tag);

        $this->makePost(); // no campaign, pillar or tag

        $byCampaign = $this->props($this->actingAs($this->owner)->get(route('planner.index', ['campaign_ids' => [$campaign->id]])));
        $byPillar = $this->props($this->actingAs($this->owner)->get(route('planner.index', ['pillar_ids' => [$pillar->id]])));
        $byTag = $this->props($this->actingAs($this->owner)->get(route('planner.index', ['tag_ids' => [$tag->id]])));

        foreach ([$byCampaign, $byPillar, $byTag] as $props) {
            $this->assertEquals([$match->id], collect($props['posts'])->pluck('id')->all());
        }
    }

    public function test_caption_search_matches_content_and_treats_wildcards_literally(): void
    {
        $target = $this->makePost(['content' => 'Save 50% this week']);
        $this->makePost(['content' => 'Something else entirely']);

        $props = $this->props($this->actingAs($this->owner)->get(route('planner.index', ['search' => '50%'])));

        $this->assertEquals([$target->id], collect($props['posts'])->pluck('id')->all());
    }

    public function test_filters_are_echoed_back_so_the_client_can_rebuild_state_from_the_url(): void
    {
        $props = $this->props($this->actingAs($this->owner)->get(route('planner.index', [
            'view' => 'week', 'search' => 'sale', 'statuses' => ['draft'],
        ])));

        $this->assertSame('sale', $props['filters']['search']);
        $this->assertSame(['draft'], $props['filters']['statuses']);
    }

    // --- Drawer -----------------------------------------------------------

    public function test_the_drawer_returns_post_detail_and_the_viewers_permissions(): void
    {
        $post = $this->makePost();

        $response = $this->actingAs($this->owner)
            ->getJson(route('planner.posts.show', $post))
            ->assertOk();

        $response->assertJsonPath('post.id', $post->id);
        $response->assertJsonPath('can.update', true);
        $response->assertJsonStructure(['post' => ['targets', 'comments', 'approvals', 'versions'], 'can']);
    }

    // --- Rescheduling -----------------------------------------------------

    public function test_a_post_can_be_dragged_to_a_new_date(): void
    {
        $post = $this->makePost();
        $target = now()->addWeek()->startOfHour();

        $this->actingAs($this->owner)
            ->patchJson(route('planner.posts.reschedule', $post), ['scheduled_at' => $target->toIso8601String()])
            ->assertOk()
            ->assertJsonPath('id', $post->id);

        $this->assertTrue($target->equalTo($post->fresh()->scheduled_at));
    }

    public function test_clearing_the_date_returns_a_scheduled_post_to_draft(): void
    {
        $post = $this->makePost();

        $this->actingAs($this->owner)
            ->patchJson(route('planner.posts.reschedule', $post), ['scheduled_at' => null])
            ->assertOk();

        $this->assertNull($post->fresh()->scheduled_at);
        $this->assertSame(Post::STATUS_DRAFT, $post->fresh()->status);
    }

    public function test_scheduling_a_draft_moves_it_to_scheduled(): void
    {
        $draft = $this->makePost(['status' => Post::STATUS_DRAFT, 'scheduled_at' => null]);

        $this->actingAs($this->owner)
            ->patchJson(route('planner.posts.reschedule', $draft), ['scheduled_at' => now()->addDay()->toIso8601String()])
            ->assertOk();

        $this->assertSame(Post::STATUS_SCHEDULED, $draft->fresh()->status);
    }

    public function test_a_publishing_post_cannot_be_rescheduled_out_from_under_the_queue(): void
    {
        $publishing = $this->makePost(['status' => Post::STATUS_PUBLISHING]);
        $original = $publishing->scheduled_at;

        $this->actingAs($this->owner)
            ->patchJson(route('planner.posts.reschedule', $publishing), ['scheduled_at' => now()->addWeek()->toIso8601String()])
            ->assertForbidden();

        $this->assertTrue($original->equalTo($publishing->fresh()->scheduled_at));
    }

    public function test_a_published_post_cannot_be_rescheduled(): void
    {
        $published = $this->makePost(['status' => Post::STATUS_PUBLISHED]);

        $this->actingAs($this->owner)
            ->patchJson(route('planner.posts.reschedule', $published), ['scheduled_at' => now()->addWeek()->toIso8601String()])
            ->assertForbidden();
    }

    // --- Bulk actions -----------------------------------------------------

    public function test_bulk_reschedule_moves_every_selected_post_to_one_moment(): void
    {
        $posts = collect(range(1, 3))->map(fn () => $this->makePost());
        $target = now()->addMonth()->startOfHour();

        $this->actingAs($this->owner)
            ->post(route('planner.bulk'), [
                'action'       => 'reschedule',
                'post_ids'     => $posts->pluck('id')->all(),
                'scheduled_at' => $target->toIso8601String(),
            ])
            ->assertSessionHasNoErrors();

        foreach ($posts as $post) {
            $this->assertTrue($target->equalTo($post->fresh()->scheduled_at));
        }
    }

    public function test_bulk_shift_moves_each_post_by_a_relative_amount(): void
    {
        $first = $this->makePost(['scheduled_at' => now()->addDay()]);
        $second = $this->makePost(['scheduled_at' => now()->addDays(3)]);

        $firstBefore = $first->scheduled_at->copy();
        $secondBefore = $second->scheduled_at->copy();

        $this->actingAs($this->owner)
            ->post(route('planner.bulk'), [
                'action'        => 'reschedule',
                'post_ids'      => [$first->id, $second->id],
                'scheduled_at'  => now()->toIso8601String(),
                'shift_minutes' => 1440,
            ])
            ->assertSessionHasNoErrors();

        // Relative, not absolute: the gap between them is preserved.
        $this->assertSame(1440, (int) $firstBefore->diffInMinutes($first->fresh()->scheduled_at));
        $this->assertSame(1440, (int) $secondBefore->diffInMinutes($second->fresh()->scheduled_at));
    }

    public function test_bulk_delete_removes_the_selection(): void
    {
        $posts = collect(range(1, 2))->map(fn () => $this->makePost());

        $this->actingAs($this->owner)
            ->post(route('planner.bulk'), ['action' => 'delete', 'post_ids' => $posts->pluck('id')->all()])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_bulk_submit_and_approve_move_a_post_through_review(): void
    {
        $post = $this->makePost(['author_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->post(route('planner.bulk'), ['action' => 'submit', 'post_ids' => [$post->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame(Post::STATUS_PENDING_APPROVAL, $post->fresh()->status);

        $this->actingAs($this->owner)
            ->post(route('planner.bulk'), ['action' => 'approve', 'post_ids' => [$post->id], 'decision' => 'approved'])
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $post->fresh()->approval_state);
    }

    public function test_bulk_tag_and_campaign_assignment_apply_to_the_selection(): void
    {
        $post = $this->makePost();
        $campaign = Campaign::create(['workspace_id' => $this->workspace->id, 'name' => 'Launch']);
        $tag = Tag::create(['workspace_id' => $this->workspace->id, 'name' => 'promo']);

        $this->actingAs($this->owner)
            ->post(route('planner.bulk'), ['action' => 'campaign', 'post_ids' => [$post->id], 'campaign_id' => $campaign->id])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->owner)
            ->post(route('planner.bulk'), ['action' => 'tag', 'post_ids' => [$post->id], 'tag_ids' => [$tag->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame($campaign->id, $post->fresh()->campaign_id);
        $this->assertTrue($post->fresh()->tags->contains($tag));
    }

    public function test_a_bulk_action_containing_one_foreign_id_is_rejected_wholesale(): void
    {
        $mine = $this->makePost();

        $stranger = User::factory()->withPersonalTeam()->create();
        $theirWorkspace = Workspace::create(['team_id' => $stranger->currentTeam->id, 'name' => 'Theirs']);
        $theirs = Post::create([
            'workspace_id' => $theirWorkspace->id,
            'content'      => 'theirs',
            'status'       => Post::STATUS_DRAFT,
        ]);

        $this->actingAs($this->owner)
            ->post(route('planner.bulk'), ['action' => 'delete', 'post_ids' => [$mine->id, $theirs->id]])
            ->assertSessionHasErrors('post_ids.1');

        // Partial application would be worse than failing: both survive.
        $this->assertDatabaseHas('posts', ['id' => $mine->id]);
        $this->assertDatabaseHas('posts', ['id' => $theirs->id]);
    }

    public function test_an_unknown_bulk_action_is_rejected(): void
    {
        $post = $this->makePost();

        $this->actingAs($this->owner)
            ->post(route('planner.bulk'), ['action' => 'publish_now', 'post_ids' => [$post->id]])
            ->assertSessionHasErrors('action');
    }

    public function test_a_bulk_action_is_recorded_in_the_audit_log(): void
    {
        $post = $this->makePost();

        $this->actingAs($this->owner)
            ->post(route('planner.bulk'), ['action' => 'delete', 'post_ids' => [$post->id]]);

        $this->assertDatabaseHas('audit_logs', [
            'action'  => 'planner.bulk_action',
            'user_id' => $this->owner->id,
        ]);
    }
}
