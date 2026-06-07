<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Post;
use App\Models\Workspace;
use Illuminate\Http\Request;

/**
 * Shared tenancy guards. A user may act on a workspace if it belongs to their
 * current organization, or they are explicitly assigned to it (client portal).
 */
trait AuthorizesWorkspaceAccess
{
    protected function guardWorkspace(Request $request, Workspace $workspace): void
    {
        $user = $request->user();

        $inOrg     = $workspace->team_id === $user->currentTeam?->id;
        $assigned  = $workspace->hasUser($user->id);

        abort_unless($inOrg || $assigned, 403);
    }

    protected function guardPost(Request $request, Post $post): void
    {
        $this->guardWorkspace($request, $post->workspace);
    }
}
