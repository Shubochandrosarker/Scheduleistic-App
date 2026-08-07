<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SuspendUserRequest;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform-wide user management. Every action here is audited; nothing
 * exposes a password, a reset token, or a session payload — only framework
 * behavior that never surfaces the secret itself.
 */
class UserController extends Controller
{
    public function index(UserIndexRequest $request): Response
    {
        $filters = $request->validated();

        $users = User::query()
            ->select([
                'id', 'name', 'email', 'email_verified_at', 'is_platform_admin',
                'suspended_at', 'suspension_reason', 'current_team_id', 'created_at',
            ])
            ->with('currentTeam:id,name')
            ->withCount(['ownedTeams', 'teams as memberships_count', 'workspaces'])
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")
            ))
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->whereNull('suspended_at'))
            ->when(($filters['status'] ?? null) === 'suspended', fn ($q) => $q->whereNotNull('suspended_at'))
            ->when(($filters['verified'] ?? null) === 'yes', fn ($q) => $q->whereNotNull('email_verified_at'))
            ->when(($filters['verified'] ?? null) === 'no', fn ($q) => $q->whereNull('email_verified_at'))
            ->when(($filters['platform_admin'] ?? null) === 'yes', fn ($q) => $q->where('is_platform_admin', true))
            ->when(($filters['platform_admin'] ?? null) === 'no', fn ($q) => $q->where('is_platform_admin', false))
            ->when($filters['organization_id'] ?? null, fn ($q, $orgId) => $q->whereHas(
                'teams', fn ($q) => $q->where('teams.id', $orgId)
            ))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->whereHas(
                'teams', fn ($q) => $q->wherePivot('role', $role)
            ))
            ->when($filters['created_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['created_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->orderBy($request->sort(), $request->direction())
            ->paginate($request->perPage())
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => $user->email_verified_at !== null,
                'is_platform_admin' => $user->is_platform_admin,
                'suspended' => $user->isSuspended(),
                'suspension_reason' => $user->suspension_reason,
                'current_team' => $user->currentTeam?->only(['id', 'name']),
                'owned_teams_count' => $user->owned_teams_count,
                'memberships_count' => $user->memberships_count,
                'workspaces_count' => $user->workspaces_count,
                'created_at' => $user->created_at,
            ]),
            'filters' => $filters,
        ]);
    }

    /** Suspend/reactivate an account. A demoted/suspended admin cannot suspend themselves. */
    public function suspend(SuspendUserRequest $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'You cannot suspend your own account.');

        $wasSuspended = $user->isSuspended();
        $reason = $request->validated('reason');

        $user->forceFill($wasSuspended
            ? ['suspended_at' => null, 'suspension_reason' => null, 'suspended_by' => null]
            : ['suspended_at' => now(), 'suspension_reason' => $reason, 'suspended_by' => $request->user()->id],
        )->save();

        AuditLog::record(
            $wasSuspended ? 'user.reactivated' : 'user.suspended',
            $user,
            ['before' => ['suspended' => $wasSuspended], 'after' => ['suspended' => ! $wasSuspended], 'reason' => $reason],
        );

        return back()->with('status', $wasSuspended ? 'user-reactivated' : 'user-suspended');
    }

    /**
     * Send the standard password-reset email — framework behavior only. The
     * token is generated and mailed by `Password::sendResetLink()`; it is
     * never returned to this response or logged anywhere.
     */
    public function sendResetLink(Request $request, User $user): RedirectResponse
    {
        $status = Password::sendResetLink(['email' => $user->email]);

        AuditLog::record('user.password_reset_link_sent', $user);

        return back()->with(
            'status',
            $status === Password::RESET_LINK_SENT ? 'reset-link-sent' : 'reset-link-failed',
        );
    }

    /**
     * Force-revoke every active session for this account by deleting its
     * rows from the `sessions` table — effective when `SESSION_DRIVER=database`
     * (the documented default; see .env.example), a no-op otherwise since
     * there would be no session rows to revoke in the first place.
     */
    public function revokeSessions(Request $request, User $user): RedirectResponse
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();

        AuditLog::record('user.sessions_revoked', $user);

        return back()->with('status', 'sessions-revoked');
    }
}
