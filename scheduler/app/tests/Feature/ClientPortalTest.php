<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ClientPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_a_client_only_sees_posts_from_workspaces_they_are_assigned_to(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $mine  = Workspace::create(['team_id' => $owner->currentTeam->id, 'name' => 'Mine']);
        $other = Workspace::create(['team_id' => $owner->currentTeam->id, 'name' => 'Other']);

        $visible = Post::create(['workspace_id' => $mine->id, 'content' => 'visible', 'status' => Post::STATUS_DRAFT]);
        Post::create(['workspace_id' => $other->id, 'content' => 'hidden', 'status' => Post::STATUS_DRAFT]);

        $client = User::factory()->withPersonalTeam()->create();
        $mine->users()->attach($client, ['role' => 'client']);

        $this->actingAs($client)
            ->get(route('posts.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Posts/Index')
                ->has('posts', 1)
                ->where('posts.0.id', $visible->id));
    }

    public function test_an_assigned_client_can_approve_a_post(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $owner->currentTeam->id, 'name' => 'Acme']);
        $post = Post::create([
            'workspace_id' => $workspace->id,
            'author_id'    => $owner->id,
            'status'       => Post::STATUS_PENDING_APPROVAL,
            'content'      => 'Approve me',
            'scheduled_at' => Carbon::now()->addDay(),
        ]);

        $client = User::factory()->withPersonalTeam()->create();
        $workspace->users()->attach($client, ['role' => 'client']);

        $this->actingAs($client)
            ->post(route('posts.decision', $post), ['decision' => 'approve'])
            ->assertRedirect();

        $this->assertSame(Post::STATUS_SCHEDULED, $post->fresh()->status);
    }

    public function test_a_user_cannot_act_on_posts_outside_their_access(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $owner->currentTeam->id, 'name' => 'Acme']);
        $post = Post::create(['workspace_id' => $workspace->id, 'content' => 'secret', 'status' => Post::STATUS_DRAFT]);

        $outsider = User::factory()->withPersonalTeam()->create();

        $this->actingAs($outsider)
            ->post(route('posts.comments.store', $post), ['body' => 'hi'])
            ->assertForbidden();
    }
}
