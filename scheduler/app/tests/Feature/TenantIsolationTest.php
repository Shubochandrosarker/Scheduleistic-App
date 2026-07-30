<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\ContentPillar;
use App\Models\Idea;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The isolation suite.
 *
 * Every 2.0 surface is asked the same question from an account that must not
 * be able to answer it. These tests exist because tenancy bugs are silent:
 * nothing errors, the wrong data simply appears.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Workspace} an owner on a plan with everything unlocked */
    protected function tenant(string $name, string $plan = 'scale'): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->currentTeam->update(['plan' => $plan]);

        $workspace = Workspace::create(['team_id' => $user->currentTeam->id, 'name' => $name]);

        return [$user, $workspace];
    }

    // --- Reads ------------------------------------------------------------

    public function test_the_planner_only_returns_posts_from_the_users_own_workspaces(): void
    {
        [$mine, $myWorkspace] = $this->tenant('Mine');
        [, $theirWorkspace] = $this->tenant('Theirs');

        $visible = Post::create([
            'workspace_id' => $myWorkspace->id,
            'content' => 'mine',
            'status' => Post::STATUS_SCHEDULED,
            'scheduled_at' => now()->addDay(),
        ]);

        Post::create([
            'workspace_id' => $theirWorkspace->id,
            'content' => 'theirs',
            'status' => Post::STATUS_SCHEDULED,
            'scheduled_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($mine)->get(route('planner.index'));

        $response->assertOk();

        $ids = collect($response->viewData('page')['props']['posts'])->pluck('id');

        $this->assertEquals([$visible->id], $ids->all());
    }

    public function test_filtering_the_planner_by_another_tenants_campaign_is_rejected(): void
    {
        [$mine] = $this->tenant('Mine');
        [, $theirWorkspace] = $this->tenant('Theirs');

        $theirCampaign = Campaign::create(['workspace_id' => $theirWorkspace->id, 'name' => 'Theirs']);

        // A validation error, not an empty result: an empty result would still
        // confirm that the id exists somewhere.
        $this->actingAs($mine)
            ->get(route('planner.index', ['campaign_ids' => [$theirCampaign->id]]))
            ->assertSessionHasErrors('campaign_ids.0');
    }

    public function test_filtering_the_planner_by_another_tenants_workspace_is_rejected(): void
    {
        [$mine] = $this->tenant('Mine');
        [, $theirWorkspace] = $this->tenant('Theirs');

        $this->actingAs($mine)
            ->get(route('planner.index', ['workspace_ids' => [$theirWorkspace->id]]))
            ->assertSessionHasErrors('workspace_ids.0');
    }

    public function test_the_media_library_only_lists_the_users_own_assets(): void
    {
        [$mine, $myWorkspace] = $this->tenant('Mine');
        [, $theirWorkspace] = $this->tenant('Theirs');

        $visible = $this->asset($myWorkspace, 'mine.jpg');
        $this->asset($theirWorkspace, 'theirs.jpg');

        $response = $this->actingAs($mine)->get(route('media.index'));

        $response->assertOk();

        $ids = collect($response->viewData('page')['props']['assets']['data'])->pluck('id');

        $this->assertEquals([$visible->id], $ids->all());
    }

    public function test_the_campaigns_page_only_lists_the_users_own_campaigns(): void
    {
        [$mine, $myWorkspace] = $this->tenant('Mine');
        [, $theirWorkspace] = $this->tenant('Theirs');

        $visible = Campaign::create(['workspace_id' => $myWorkspace->id, 'name' => 'Mine']);
        Campaign::create(['workspace_id' => $theirWorkspace->id, 'name' => 'Theirs']);

        $response = $this->actingAs($mine)->get(route('campaigns.index'));

        $ids = collect($response->viewData('page')['props']['campaigns']['data'])->pluck('id');

        $this->assertEquals([$visible->id], $ids->all());
    }

    public function test_the_ideas_board_only_lists_the_users_own_ideas(): void
    {
        [$mine, $myWorkspace] = $this->tenant('Mine');
        [, $theirWorkspace] = $this->tenant('Theirs');

        $visible = Idea::create(['workspace_id' => $myWorkspace->id, 'title' => 'Mine']);
        Idea::create(['workspace_id' => $theirWorkspace->id, 'title' => 'Theirs']);

        $response = $this->actingAs($mine)->get(route('ideas.index'));

        $ids = collect($response->viewData('page')['props']['ideas'])->pluck('id');

        $this->assertEquals([$visible->id], $ids->all());
    }

    public function test_the_health_page_only_lists_the_users_own_profiles(): void
    {
        [$mine, $myWorkspace] = $this->tenant('Mine');
        [, $theirWorkspace] = $this->tenant('Theirs');

        $visible = $myWorkspace->channels()->create(['provider' => 'linkedin', 'name' => 'Mine']);
        $theirWorkspace->channels()->create(['provider' => 'linkedin', 'name' => 'Theirs']);

        $response = $this->actingAs($mine)->get(route('channels.health'));

        $ids = collect($response->viewData('page')['props']['channels'])->pluck('id');

        $this->assertEquals([$visible->id], $ids->all());
    }

    // --- Writes -----------------------------------------------------------

    public function test_a_campaign_cannot_be_created_in_another_tenants_workspace(): void
    {
        [$mine] = $this->tenant('Mine');
        [, $theirWorkspace] = $this->tenant('Theirs');

        $this->actingAs($mine)
            ->post(route('campaigns.store'), ['workspace_id' => $theirWorkspace->id, 'name' => 'Sneaky'])
            ->assertSessionHasErrors('workspace_id');

        $this->assertDatabaseCount('campaigns', 0);
    }

    public function test_an_idea_cannot_reference_another_tenants_campaign(): void
    {
        [$mine, $myWorkspace] = $this->tenant('Mine');
        [, $theirWorkspace] = $this->tenant('Theirs');

        $theirCampaign = Campaign::create(['workspace_id' => $theirWorkspace->id, 'name' => 'Theirs']);

        $this->actingAs($mine)
            ->post(route('ideas.store'), [
                'workspace_id' => $myWorkspace->id,
                'title'        => 'Borrowed campaign',
                'campaign_id'  => $theirCampaign->id,
            ])
            ->assertSessionHasErrors('campaign_id');
    }

    public function test_another_tenants_campaign_cannot_be_updated_or_deleted(): void
    {
        [$mine] = $this->tenant('Mine');
        [, $theirWorkspace] = $this->tenant('Theirs');

        $theirs = Campaign::create(['workspace_id' => $theirWorkspace->id, 'name' => 'Theirs']);

        $this->actingAs($mine)->put(route('campaigns.update', $theirs), ['name' => 'Hijacked'])->assertForbidden();
        $this->actingAs($mine)->delete(route('campaigns.destroy', $theirs))->assertForbidden();

        $this->assertSame('Theirs', $theirs->fresh()->name);
    }

    public function test_another_tenants_idea_cannot_be_converted_into_posts(): void
    {
        [$mine] = $this->tenant('Mine');
        [, $theirWorkspace] = $this->tenant('Theirs');

        $theirs = Idea::create(['workspace_id' => $theirWorkspace->id, 'title' => 'Theirs']);

        $this->actingAs($mine)->post(route('ideas.convert', $theirs), ['count' => 1])->assertForbidden();

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_another_tenants_asset_cannot_be_read_updated_or_deleted(): void
    {
        [$mine] = $this->tenant('Mine');
        [, $theirWorkspace] = $this->tenant('Theirs');

        $theirs = $this->asset($theirWorkspace, 'theirs.jpg');

        $this->actingAs($mine)->get(route('media.status', $theirs))->assertForbidden();
        $this->actingAs($mine)->put(route('media.update', $theirs), ['alt_text' => 'x'])->assertForbidden();
        $this->actingAs($mine)->delete(route('media.destroy', $theirs))->assertForbidden();

        $this->assertDatabaseHas('media_assets', ['id' => $theirs->id, 'deleted_at' => null]);
    }

    public function test_another_tenants_post_cannot_be_opened_rescheduled_or_bulk_edited(): void
    {
        [$mine] = $this->tenant('Mine');
        [, $theirWorkspace] = $this->tenant('Theirs');

        $theirs = Post::create([
            'workspace_id' => $theirWorkspace->id,
            'content'      => 'theirs',
            'status'       => Post::STATUS_SCHEDULED,
            'scheduled_at' => now()->addDay(),
        ]);

        $this->actingAs($mine)->get(route('planner.posts.show', $theirs))->assertForbidden();

        $this->actingAs($mine)
            ->patch(route('planner.posts.reschedule', $theirs), ['scheduled_at' => now()->addWeek()->toIso8601String()])
            ->assertForbidden();

        // Bulk rejects at validation: the id is not in the caller's tenancy.
        $this->actingAs($mine)
            ->post(route('planner.bulk'), ['action' => 'delete', 'post_ids' => [$theirs->id]])
            ->assertSessionHasErrors('post_ids.0');

        $this->assertDatabaseHas('posts', ['id' => $theirs->id]);
    }

    public function test_a_pillar_or_tag_from_another_tenant_cannot_be_deleted(): void
    {
        [$mine] = $this->tenant('Mine');
        [, $theirWorkspace] = $this->tenant('Theirs');

        $pillar = ContentPillar::create(['workspace_id' => $theirWorkspace->id, 'name' => 'Theirs']);
        $tag = Tag::create(['workspace_id' => $theirWorkspace->id, 'name' => 'Theirs']);

        $this->actingAs($mine)->delete(route('pillars.destroy', $pillar))->assertForbidden();
        $this->actingAs($mine)->delete(route('tags.destroy', $tag))->assertForbidden();
    }

    // --- Client portal ----------------------------------------------------

    public function test_an_assigned_client_sees_only_the_workspace_they_are_assigned_to(): void
    {
        [$owner, $assigned] = $this->tenant('Assigned');
        $other = Workspace::create(['team_id' => $owner->currentTeam->id, 'name' => 'Other']);

        $visible = Post::create([
            'workspace_id' => $assigned->id,
            'content'      => 'visible',
            'status'       => Post::STATUS_SCHEDULED,
            'scheduled_at' => now()->addDay(),
        ]);

        Post::create([
            'workspace_id' => $other->id,
            'content'      => 'hidden',
            'status'       => Post::STATUS_SCHEDULED,
            'scheduled_at' => now()->addDay(),
        ]);

        $client = User::factory()->withPersonalTeam()->create();
        $assigned->users()->attach($client, ['role' => 'client']);

        $response = $this->actingAs($client)->get(route('planner.index'));

        $ids = collect($response->viewData('page')['props']['posts'])->pluck('id');

        $this->assertEquals([$visible->id], $ids->all());
    }

    protected function asset(Workspace $workspace, string $name): MediaAsset
    {
        return MediaAsset::create([
            'workspace_id'      => $workspace->id,
            'name'              => $name,
            'kind'              => MediaAsset::KIND_IMAGE,
            'mime'              => 'image/jpeg',
            'disk'              => 'local',
            'path'              => "media/{$workspace->id}/".uniqid().'/original.jpg',
            'size'              => 1000,
            'checksum'          => hash('sha256', $workspace->id.$name),
            'processing_status' => MediaAsset::STATUS_READY,
        ]);
    }
}
