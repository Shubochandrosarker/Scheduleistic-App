<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PostApprovalDecided;
use App\Notifications\PostSubmittedForApproval;
use Illuminate\Support\Facades\Notification;

/**
 * Drives the post review loop: submit → pending → approve / reject /
 * changes-requested, keeping post.status + post.approval_state in sync and
 * notifying the right people.
 */
class ApprovalService
{
    /** Submit a draft for review and notify the workspace's approvers. */
    public function submit(Post $post, User $requester): Approval
    {
        $post->update([
            'status'         => Post::STATUS_PENDING_APPROVAL,
            'approval_state' => Approval::STATE_PENDING,
        ]);

        $approval = $post->approvals()->create([
            'requested_by' => $requester->id,
            'state'        => Approval::STATE_PENDING,
        ]);

        $approvers = $this->approversFor($post)->reject(fn (User $u) => $u->is($requester));

        if ($approvers->isNotEmpty()) {
            Notification::send($approvers, new PostSubmittedForApproval($post));
        }

        return $approval;
    }

    /** Approve the post. If it was scheduled, it returns to the schedule queue. */
    public function approve(Post $post, User $approver, ?string $comment = null): void
    {
        $this->decide($post, $approver, Approval::STATE_APPROVED, $comment);

        $post->update([
            'approval_state' => Approval::STATE_APPROVED,
            'status'         => $post->scheduled_at ? Post::STATUS_SCHEDULED : Post::STATUS_DRAFT,
        ]);

        $this->notifyAuthor($post, 'approved', $comment);
    }

    public function reject(Post $post, User $approver, ?string $comment = null): void
    {
        $this->decide($post, $approver, Approval::STATE_REJECTED, $comment);

        $post->update([
            'approval_state' => Approval::STATE_REJECTED,
            'status'         => Post::STATUS_DRAFT,
        ]);

        $this->notifyAuthor($post, 'rejected', $comment);
    }

    public function requestChanges(Post $post, User $approver, ?string $comment = null): void
    {
        $this->decide($post, $approver, Approval::STATE_CHANGES_REQUESTED, $comment);

        $post->update([
            'approval_state' => Approval::STATE_CHANGES_REQUESTED,
            'status'         => Post::STATUS_DRAFT,
        ]);

        $this->notifyAuthor($post, 'sent back for changes', $comment);
    }

    protected function decide(Post $post, User $approver, string $state, ?string $comment): void
    {
        $approval = $post->approvals()
            ->where('state', Approval::STATE_PENDING)
            ->latest()
            ->first()
            ?? $post->approvals()->create(['requested_by' => $approver->id, 'state' => Approval::STATE_PENDING]);

        $approval->update([
            'decided_by' => $approver->id,
            'state'      => $state,
            'comment'    => $comment,
            'decided_at' => now(),
        ]);
    }

    protected function notifyAuthor(Post $post, string $decision, ?string $comment): void
    {
        $post->author?->notify(new PostApprovalDecided($post, $decision, $comment));
    }

    /**
     * Users who can approve a post: organization owner/admins plus workspace
     * members/approvers/clients assigned to the post's workspace.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function approversFor(Post $post): \Illuminate\Support\Collection
    {
        $workspace = $post->workspace;

        return $workspace->users()
            ->wherePivotIn('role', ['approver', 'client', 'member'])
            ->get()
            ->push($workspace->team->owner)
            ->filter()
            ->unique('id')
            ->values();
    }
}
