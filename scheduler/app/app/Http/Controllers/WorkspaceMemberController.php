<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspaceAccess;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Assign organization members (and clients) to a workspace with a role.
 * Only org owner/admins may manage assignments.
 */
class WorkspaceMemberController extends Controller
{
    use AuthorizesWorkspaceAccess;

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->guardManage($request, $workspace);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role'    => ['required', 'in:member,approver,client'],
        ]);

        $workspace->users()->syncWithoutDetaching([
            $validated['user_id'] => ['role' => $validated['role']],
        ]);

        return back()->with('status', 'member-assigned');
    }

    public function destroy(Request $request, Workspace $workspace, User $user): RedirectResponse
    {
        $this->guardManage($request, $workspace);

        $workspace->users()->detach($user->id);

        return back()->with('status', 'member-removed');
    }

    protected function guardManage(Request $request, Workspace $workspace): void
    {
        $user = $request->user();
        $team = $user->currentTeam;

        abort_unless($workspace->team_id === $team?->id, 403);
        abort_unless(
            $team->owner->is($user) || $user->hasTeamRole($team, 'admin'),
            403,
            'Only organization owners and admins can manage workspace members.',
        );
    }
}
