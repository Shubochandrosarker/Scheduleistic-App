<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PostCollaborationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function pendingPost(): array
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $owner->currentTeam->id, 'name' => 'Acme']);
        $approver = User::factory()->create();
        $workspace->users()->attach($approver, ['role' => 'approver']);

        $post = Post::create([
            'workspace_id'   => $workspace->id,
            'author_id'      => $owner->id,
            'status'         => Post::STATUS_PENDING_APPROVAL,
            'approval_state' => Approval::STATE_PENDING,
            'content'        => 'Review me',
            'scheduled_at'   => Carbon::now()->addDay(),
        ]);
        $post->approvals()->create(['requested_by' => $owner->id, 'state' => Approval::STATE_PENDING]);

        return [$owner, $approver, $workspace, $post];
    }

    public function test_requesting_changes_over_http_sends_the_post_back_to_draft_with_a_comment(): void
    {
        [, $approver, , $post] = $this->pendingPost();

        $this->actingAs($approver)
            ->post(route('posts.decision', $post), [
                'decision' => 'request_changes',
                'comment'  => 'Tighten the hook',
            ])
            ->assertRedirect();

        $post->refresh();
        $this->assertSame(Post::STATUS_DRAFT, $post->status);
        $this->assertSame(Approval::STATE_CHANGES_REQUESTED, $post->approval_state);
        $this->assertSame('Tighten the hook', $post->approvals()->latest()->first()->comment);
    }

    public function test_a_comment_with_a_mention_is_stored_and_visible_on_the_posts_page(): void
    {
        [$owner, $approver, , $post] = $this->pendingPost();

        $this->actingAs($owner)
            ->post(route('posts.comments.store', $post), [
                'body'     => "Cc @{$approver->name}",
                'mentions' => [$approver->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', [
            'post_id' => $post->id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('posts.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Posts/Index')
                ->has('posts.0.comments', 1)
                ->where('posts.0.comments.0.user.name', $owner->name)
                ->where('posts.0.comments.0.mentions.0', $approver->id));
    }

    public function test_posts_index_exposes_mentionable_workspace_members(): void
    {
        [$owner, $approver, $workspace, $post] = $this->pendingPost();

        $this->actingAs($owner)
            ->get(route('posts.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Posts/Index')
                ->has("workspaceMembers.{$workspace->id}", 2) // owner + approver
                ->where("workspaceMembers.{$workspace->id}", fn ($members) => $members->pluck('id')->contains($approver->id)));
    }
}
