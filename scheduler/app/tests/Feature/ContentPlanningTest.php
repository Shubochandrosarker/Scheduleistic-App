<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\ContentPillar;
use App\Models\Idea;
use App\Models\NotificationPreference;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentPlanningTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withPersonalTeam()->create();
        $this->owner->currentTeam->update(['plan' => 'scale']);
        $this->workspace = Workspace::create(['team_id' => $this->owner->currentTeam->id, 'name' => 'Brand']);
    }

    // --- Workspace bootstrap ---------------------------------------------

    public function test_a_new_workspace_is_seeded_with_default_content_pillars(): void
    {
        $this->actingAs($this->owner)
            ->post(route('workspaces.store'), ['name' => 'Second brand'])
            ->assertSessionHasNoErrors();

        $workspace = Workspace::where('name', 'Second brand')->firstOrFail();

        $this->assertSame(count(ContentPillar::DEFAULTS), $workspace->pillars()->count());
        $this->assertTrue($workspace->pillars()->where('name', 'Education')->exists());
    }

    public function test_seeding_pillars_is_safe_to_repeat(): void
    {
        $this->workspace->seedDefaultPillars();
        $this->workspace->seedDefaultPillars();

        $this->assertSame(count(ContentPillar::DEFAULTS), $this->workspace->pillars()->count());
    }

    // --- Campaigns --------------------------------------------------------

    public function test_a_campaign_can_be_created_with_utm_defaults(): void
    {
        $this->actingAs($this->owner)
            ->post(route('campaigns.store'), [
                'workspace_id' => $this->workspace->id,
                'name'         => 'Spring launch',
                'goal'         => '500 signups',
                'target_url'   => 'https://example.com/spring',
                'utm'          => ['source' => 'social', 'medium' => 'organic', 'campaign' => 'spring launch'],
            ])
            ->assertSessionHasNoErrors();

        $campaign = Campaign::firstOrFail();

        $this->assertSame('Spring launch', $campaign->name);
        $this->assertNotNull($campaign->slug);
        // Values are urlencoded once, at the model, so every consumer produces
        // the same URL and attribution does not fragment.
        $this->assertSame(
            'utm_source=social&utm_medium=organic&utm_campaign=spring+launch',
            $campaign->utmQuery(),
        );
    }

    public function test_a_campaign_with_an_end_date_before_its_start_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->post(route('campaigns.store'), [
                'workspace_id' => $this->workspace->id,
                'name'         => 'Backwards',
                'starts_on'    => '2026-06-01',
                'ends_on'      => '2026-05-01',
            ])
            ->assertSessionHasErrors('ends_on');
    }

    public function test_deleting_a_campaign_keeps_its_posts(): void
    {
        $campaign = Campaign::create(['workspace_id' => $this->workspace->id, 'name' => 'Launch']);

        $post = Post::create([
            'workspace_id' => $this->workspace->id,
            'campaign_id'  => $campaign->id,
            'content'      => 'Published already',
            'status'       => Post::STATUS_PUBLISHED,
        ]);

        $this->actingAs($this->owner)
            ->delete(route('campaigns.destroy', $campaign))
            ->assertSessionHasNoErrors();

        $post->refresh();

        $this->assertNull($post->campaign_id);
        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }

    public function test_campaigns_require_the_capability(): void
    {
        $this->owner->currentTeam->update(['plan' => 'solo']);

        $this->actingAs($this->owner)->get(route('campaigns.index'))->assertSessionHasErrors('plan');
    }

    // --- Tags -------------------------------------------------------------

    public function test_creating_a_tag_that_already_exists_does_not_duplicate_it(): void
    {
        foreach (['Evergreen', 'evergreen', ' EVERGREEN '] as $name) {
            $this->actingAs($this->owner)->post(route('tags.store'), [
                'workspace_id' => $this->workspace->id,
                'name'         => $name,
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(1, Tag::where('workspace_id', $this->workspace->id)->count());
    }

    public function test_the_same_tag_name_can_exist_in_two_workspaces(): void
    {
        $second = Workspace::create(['team_id' => $this->owner->currentTeam->id, 'name' => 'Second']);

        Tag::resolve($this->workspace->id, 'promo');
        Tag::resolve($second->id, 'promo');

        $this->assertSame(2, Tag::where('slug', 'promo')->count());
    }

    // --- Ideas ------------------------------------------------------------

    public function test_an_idea_can_be_captured_with_tags(): void
    {
        $this->actingAs($this->owner)
            ->post(route('ideas.store'), [
                'workspace_id' => $this->workspace->id,
                'title'        => 'Behind the scenes video',
                'notes'        => 'Film the workshop',
                'priority'     => 'high',
                'tags'         => ['video', 'culture'],
            ])
            ->assertSessionHasNoErrors();

        $idea = Idea::firstOrFail();

        $this->assertSame('inbox', $idea->stage);
        $this->assertSame($this->owner->id, $idea->author_id);
        $this->assertSame(2, $idea->tags()->count());
    }

    public function test_an_invalid_stage_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->post(route('ideas.store'), [
                'workspace_id' => $this->workspace->id,
                'title'        => 'Something',
                'stage'        => 'marinating',
            ])
            ->assertSessionHasErrors('stage');
    }

    public function test_converting_an_idea_creates_drafts_and_keeps_the_idea(): void
    {
        $campaign = Campaign::create(['workspace_id' => $this->workspace->id, 'name' => 'Launch']);

        $idea = Idea::create([
            'workspace_id' => $this->workspace->id,
            'campaign_id'  => $campaign->id,
            'title'        => 'Three tips post',
            'notes'        => 'Tip one, tip two, tip three',
        ]);

        $this->actingAs($this->owner)
            ->post(route('ideas.convert', $idea), ['count' => 3])
            ->assertRedirect(route('planner.index'));

        $this->assertSame(3, Post::count());

        $post = Post::first();

        $this->assertSame(Post::STATUS_DRAFT, $post->status);
        $this->assertSame($idea->id, $post->idea_id);
        // The campaign carries across, so converted drafts stay attributed.
        $this->assertSame($campaign->id, $post->campaign_id);

        // The idea survives and advances, rather than being consumed.
        $this->assertSame('drafting', $idea->fresh()->stage);
    }

    public function test_converting_more_than_ten_posts_at_once_is_rejected(): void
    {
        $idea = Idea::create(['workspace_id' => $this->workspace->id, 'title' => 'Spam']);

        $this->actingAs($this->owner)
            ->post(route('ideas.convert', $idea), ['count' => 500])
            ->assertSessionHasErrors('count');

        $this->assertSame(0, Post::count());
    }

    // --- Notification preferences -----------------------------------------

    public function test_notification_preferences_default_to_the_documented_values(): void
    {
        $this->owner->load('notificationPreferences');

        $preference = NotificationPreference::resolve($this->owner, 'publish_failed');

        $this->assertTrue($preference['in_app']);
        $this->assertTrue($preference['email']);
        $this->assertSame('immediate', $preference['digest']);
    }

    public function test_notification_preferences_can_be_saved(): void
    {
        $this->actingAs($this->owner)
            ->put(route('notifications.preferences'), [
                'preferences' => [
                    ['event' => 'publish_failed', 'in_app' => true, 'email' => false, 'digest' => 'daily'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $this->owner->id,
            'event'   => 'publish_failed',
            'email'   => false,
            'digest'  => 'daily',
        ]);
    }

    public function test_an_unknown_notification_event_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->put(route('notifications.preferences'), [
                'preferences' => [
                    ['event' => 'invented_event', 'in_app' => true, 'email' => true, 'digest' => 'daily'],
                ],
            ])
            ->assertSessionHasErrors('preferences.0.event');
    }

    public function test_the_notification_centre_renders(): void
    {
        $this->actingAs($this->owner)
            ->get(route('notifications.index'))
            ->assertOk();

        $props = $this->actingAs($this->owner)->get(route('notifications.index'))->viewData('page')['props'];

        $this->assertSame(count(NotificationPreference::EVENTS), count($props['events']));
    }
}
