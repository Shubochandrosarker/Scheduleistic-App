<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

/**
 * Posts add one verb the generic policy does not have: approve. Approving is
 * deliberately separate from updating so an Approver or Client role can sign
 * off on content they are not allowed to edit.
 */
class PostPolicy extends WorkspaceScopedPolicy
{
    protected string $createPermission = 'post:create';

    protected string $updatePermission = 'post:update';

    protected string $deletePermission = 'post:delete';

    public function approve(User $user, Post $post): bool
    {
        return $this->inTenancy($user, $post)
            && $this->hasPermission($user, 'post:approve');
    }

    /**
     * Rescheduling is an update, but the planner does it by drag-and-drop on
     * posts that may already be publishing — and a post mid-flight must not
     * move under the queue's feet.
     */
    public function reschedule(User $user, Post $post): bool
    {
        return $this->update($user, $post)
            && ! in_array($post->status, [Post::STATUS_PUBLISHING, Post::STATUS_PUBLISHED], true);
    }
}
