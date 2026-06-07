<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\PostApprovalDecided;
use App\Notifications\PostSubmittedForApproval;
use App\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function setup_workspace_with_author_and_approver(): array
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $owner->currentTeam->id, 'name' => 'Acme']);

        $approver = User::factory()->create();
        $workspace->users()->attach($approver, ['role' => 'approver']);

        $post = Post::create([
            'workspace_id' => $workspace->id,
            'author_id'    => $owner->id,
            'status'       => Post::STATUS_DRAFT,
            'content'      => 'Review me',
            'scheduled_at' => Carbon::now()->addDay(),
        ]);

        return [$owner, $approver, $workspace, $post];
    }

    public function test_submitting_moves_post_to_pending_and_notifies_approvers(): void
    {
        Notification::fake();
        [$owner, $approver, , $post] = $this->setup_workspace_with_author_and_approver();

        app(ApprovalService::class)->submit($post, $owner);

        $post->refresh();
        $this->assertSame(Post::STATUS_PENDING_APPROVAL, $post->status);
        $this->assertSame(Approval::STATE_PENDING, $post->approval_state);

        Notification::assertSentTo($approver, PostSubmittedForApproval::class);
    }

    public function test_approving_a_scheduled_post_returns_it_to_the_schedule(): void
    {
        Notification::fake();
        [$owner, $approver, , $post] = $this->setup_workspace_with_author_and_approver();

        app(ApprovalService::class)->submit($post, $owner);
        app(ApprovalService::class)->approve($post, $approver, 'Looks good');

        $post->refresh();
        $this->assertSame(Approval::STATE_APPROVED, $post->approval_state);
        $this->assertSame(Post::STATUS_SCHEDULED, $post->status);

        Notification::assertSentTo($owner, PostApprovalDecided::class);
    }

    public function test_rejecting_sends_the_post_back_to_draft(): void
    {
        Notification::fake();
        [$owner, $approver, , $post] = $this->setup_workspace_with_author_and_approver();

        app(ApprovalService::class)->submit($post, $owner);
        app(ApprovalService::class)->reject($post, $approver, 'Off brand');

        $post->refresh();
        $this->assertSame(Approval::STATE_REJECTED, $post->approval_state);
        $this->assertSame(Post::STATUS_DRAFT, $post->status);
    }

    public function test_decision_is_recorded_on_the_approval(): void
    {
        Notification::fake();
        [$owner, $approver, , $post] = $this->setup_workspace_with_author_and_approver();

        app(ApprovalService::class)->submit($post, $owner);
        app(ApprovalService::class)->requestChanges($post, $approver, 'Tighten the hook');

        $approval = $post->approvals()->latest()->first();
        $this->assertSame(Approval::STATE_CHANGES_REQUESTED, $approval->state);
        $this->assertTrue($approval->decided_by === $approver->id);
        $this->assertSame('Tighten the hook', $approval->comment);
        $this->assertNotNull($approval->decided_at);
    }
}
