<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;

/**
 * Base policy for every model that hangs off a workspace.
 *
 * Two questions, always in this order:
 *
 *  1. **Tenancy** — is this record inside a workspace the user can see at all?
 *     Owned by their current organization, or individually assigned to them.
 *     This is absolute; no permission overrides it.
 *  2. **Permission** — does their organization role allow the action?
 *
 * Subclasses only name the Jetstream permission for each verb. They never
 * re-implement the tenancy check, which is exactly the failure mode the audit
 * flagged: a new controller that forgets to guard.
 */
abstract class WorkspaceScopedPolicy
{
    /** Jetstream permission required to create, e.g. 'post:create'. */
    protected string $createPermission = 'post:create';

    /** Jetstream permission required to update. */
    protected string $updatePermission = 'post:update';

    /** Jetstream permission required to delete. */
    protected string $deletePermission = 'post:delete';

    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null || $user->workspaces()->exists();
    }

    public function view(User $user, Model $model): bool
    {
        return $this->inTenancy($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, $this->createPermission);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->inTenancy($user, $model)
            && $this->hasPermission($user, $this->updatePermission);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->inTenancy($user, $model)
            && $this->hasPermission($user, $this->deletePermission);
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->delete($user, $model);
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $this->inTenancy($user, $model) && $this->isOwnerOrAdmin($user);
    }

    // --- Shared checks --------------------------------------------------

    /** The record's workspace is one the user may see. */
    protected function inTenancy(User $user, Model $model): bool
    {
        $workspaceId = $this->workspaceIdFor($model);

        if ($workspaceId === null) {
            return false;
        }

        return $user->canAccessWorkspace($workspaceId);
    }

    /**
     * Resolve the owning workspace id. Models that are not directly
     * workspace-scoped (a PostTarget, say) override this.
     */
    protected function workspaceIdFor(Model $model): ?int
    {
        $id = $model->getAttribute('workspace_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * The organization owner has every permission implicitly; invited members
     * are checked against their Jetstream role. A client-portal user with no
     * organization membership is allowed the actions their workspace role
     * implies, which is why assignment alone is enough for view.
     */
    protected function hasPermission(User $user, string $permission): bool
    {
        $team = $user->currentTeam;

        if ($team === null) {
            return false;
        }

        if ($user->ownsTeam($team)) {
            return true;
        }

        return $user->hasTeamPermission($team, $permission);
    }

    protected function isOwnerOrAdmin(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null
            && ($user->ownsTeam($team) || $user->hasTeamRole($team, 'admin'));
    }

    /** Guard used when creating: the target workspace must be in tenancy. */
    public function createIn(User $user, Workspace $workspace): bool
    {
        return $user->canAccessWorkspace($workspace->id)
            && $this->hasPermission($user, $this->createPermission);
    }
}
