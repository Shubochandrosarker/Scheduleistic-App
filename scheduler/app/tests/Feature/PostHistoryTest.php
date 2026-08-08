<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Channel;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The permanent, unbounded post/schedule history page — every past publish
 * attempt across every channel, distinct from the planner's date-windowed
 * `limit(500)` view.
 */
class PostHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withPersonalTeam()->create();
        $this->workspace = Workspace::create(['team_id' => $this->owner->currentTeam->id, 'name' => 'Brand']);
    }

    protected function channel(?Workspace $workspace = null, string $provider = 'linkedin'): Channel
    {
        return ($workspace ?? $this->workspace)->channels()->create(['provider' => $provider, 'name' => ucfirst($provider).' page']);
    }

    protected function target(Channel $channel, array $postAttributes = [], array $targetAttributes = []): PostTarget
    {
        $post = Post::create(array_merge([
            'workspace_id' => $channel->workspace_id,
            'content' => 'A post',
            'status' => Post::STATUS_PUBLISHED,
            'scheduled_at' => now()->subDay(),
            'published_at' => now()->subDay(),
            'timezone' => 'UTC',
        ], $postAttributes));

        return PostTarget::create(array_merge([
            'post_id' => $post->id,
            'channel_id' => $channel->id,
            'status' => PostTarget::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'attempts' => 1,
        ], $targetAttributes));
    }

    // --- Access & rendering -------------------------------------------

    public function test_a_guest_cannot_view_history(): void
    {
        $this->get(route('history.index'))->assertRedirect(route('login'));
    }

    public function test_it_renders_with_filter_options_and_a_summary(): void
    {
        $channel = $this->channel();
        $this->target($channel);

        $this->actingAs($this->owner)
            ->get(route('history.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('History/Index')
                ->has('targets.data', 1)
                ->has('options.workspaces')
                ->has('options.statuses')
                ->has('summary.by_status'));
    }

    public function test_a_user_cannot_see_another_tenants_history(): void
    {
        $stranger = User::factory()->withPersonalTeam()->create();
        $theirWorkspace = Workspace::create(['team_id' => $stranger->currentTeam->id, 'name' => 'Theirs']);
        $theirChannel = $this->channel($theirWorkspace);
        $this->target($theirChannel);

        $this->target($this->channel());

        $this->actingAs($this->owner)
            ->get(route('history.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('targets.data', 1));
    }

    public function test_requesting_a_workspace_outside_the_users_tenancy_is_rejected(): void
    {
        $stranger = User::factory()->withPersonalTeam()->create();
        $theirWorkspace = Workspace::create(['team_id' => $stranger->currentTeam->id, 'name' => 'Theirs']);

        $this->actingAs($this->owner)
            ->get(route('history.index', ['workspace_ids' => [$theirWorkspace->id]]))
            ->assertSessionHasErrors('workspace_ids.0');
    }

    // --- Filters --------------------------------------------------------

    public function test_it_filters_by_status(): void
    {
        $channel = $this->channel();
        $this->target($channel, [], ['status' => PostTarget::STATUS_PUBLISHED]);
        $this->target($channel, [], ['status' => PostTarget::STATUS_FAILED, 'error' => 'Token expired']);

        $this->actingAs($this->owner)
            ->get(route('history.index', ['statuses' => [PostTarget::STATUS_FAILED]]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('targets.data', 1)
                ->where('targets.data.0.status', PostTarget::STATUS_FAILED)
                ->where('targets.data.0.error', 'Token expired'));
    }

    public function test_it_filters_by_provider(): void
    {
        $linkedin = $this->channel(null, 'linkedin');
        $mastodon = $this->channel(null, 'mastodon');
        $this->target($linkedin);
        $this->target($mastodon);

        $this->actingAs($this->owner)
            ->get(route('history.index', ['providers' => ['mastodon']]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('targets.data', 1)
                ->where('targets.data.0.channel.provider', 'mastodon'));
    }

    public function test_it_filters_by_campaign(): void
    {
        $channel = $this->channel();
        $campaign = Campaign::create(['workspace_id' => $this->workspace->id, 'name' => 'Launch']);

        $this->target($channel, ['campaign_id' => $campaign->id]);
        $this->target($channel); // no campaign

        $this->actingAs($this->owner)
            ->get(route('history.index', ['campaign_ids' => [$campaign->id]]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('targets.data', 1)
                ->where('targets.data.0.post.campaign.id', $campaign->id));
    }

    public function test_it_filters_by_date_range(): void
    {
        $channel = $this->channel();
        $this->target($channel, ['scheduled_at' => now()->subMonths(2)]);
        $recent = $this->target($channel, ['scheduled_at' => now()->subDay()]);

        $this->actingAs($this->owner)
            ->get(route('history.index', ['from' => now()->subWeek()->toDateString()]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('targets.data', 1)
                ->where('targets.data.0.id', $recent->id));
    }

    public function test_it_searches_post_content_and_title(): void
    {
        $channel = $this->channel();
        $this->target($channel, ['title' => 'Summer sale', 'content' => 'Get 20% off']);
        $this->target($channel, ['title' => 'Winter clearance', 'content' => 'Something else']);

        $this->actingAs($this->owner)
            ->get(route('history.index', ['search' => 'summer']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('targets.data', 1));
    }

    public function test_a_percent_sign_in_search_is_escaped_and_not_treated_as_a_wildcard(): void
    {
        $channel = $this->channel();
        $this->target($channel, ['content' => 'Get 50% off today']);
        $this->target($channel, ['content' => 'Nothing special here']);

        $this->actingAs($this->owner)
            ->get(route('history.index', ['search' => '50%']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('targets.data', 1));
    }

    // --- Sorting ----------------------------------------------------------

    public function test_it_sorts_by_scheduled_at_descending_by_default(): void
    {
        $channel = $this->channel();
        $older = $this->target($channel, ['scheduled_at' => now()->subDays(5)]);
        $newer = $this->target($channel, ['scheduled_at' => now()->subDay()]);

        $this->actingAs($this->owner)
            ->get(route('history.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('targets.data.0.id', $newer->id)
                ->where('targets.data.1.id', $older->id));
    }

    public function test_it_can_sort_ascending(): void
    {
        $channel = $this->channel();
        $older = $this->target($channel, ['scheduled_at' => now()->subDays(5)]);
        $newer = $this->target($channel, ['scheduled_at' => now()->subDay()]);

        $this->actingAs($this->owner)
            ->get(route('history.index', ['sort' => 'scheduled_at', 'direction' => 'asc']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('targets.data.0.id', $older->id)
                ->where('targets.data.1.id', $newer->id));
    }

    // --- Summary / breakdowns ----------------------------------------------

    public function test_the_summary_breaks_down_by_status_campaign_and_tag(): void
    {
        $channel = $this->channel();
        $campaign = Campaign::create(['workspace_id' => $this->workspace->id, 'name' => 'Launch']);
        $tag = Tag::create(['workspace_id' => $this->workspace->id, 'name' => 'evergreen']);

        $published = $this->target($channel, ['campaign_id' => $campaign->id], ['status' => PostTarget::STATUS_PUBLISHED]);
        $published->post->tags()->attach($tag);

        $this->target($channel, [], ['status' => PostTarget::STATUS_FAILED]);

        $props = $this->actingAs($this->owner)->get(route('history.index'))->viewData('page')['props'];

        $this->assertSame(1, $props['summary']['by_status']['published']);
        $this->assertSame(1, $props['summary']['by_status']['failed']);
        $this->assertSame($campaign->id, $props['summary']['by_campaign'][0]['id']);
        $this->assertSame(1, $props['summary']['by_campaign'][0]['total']);
        $this->assertSame($tag->id, $props['summary']['by_tag'][0]['id']);
    }

    // --- CSV export ---------------------------------------------------------

    public function test_export_streams_a_csv_of_the_filtered_set(): void
    {
        $linkedin = $this->channel(null, 'linkedin');
        $mastodon = $this->channel(null, 'mastodon');
        $this->target($linkedin, ['title' => 'Kept'], ['status' => PostTarget::STATUS_PUBLISHED]);
        $this->target($mastodon, ['title' => 'Dropped'], ['status' => PostTarget::STATUS_FAILED]);

        $response = $this->actingAs($this->owner)
            ->get(route('history.export', ['providers' => ['linkedin']]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Kept', $csv);
        $this->assertStringNotContainsString('Dropped', $csv);
    }

    public function test_a_guest_cannot_export(): void
    {
        $this->get(route('history.export'))->assertRedirect(route('login'));
    }
}
