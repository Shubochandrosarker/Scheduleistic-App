<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The single place that starts, stops, and expires a platform admin's support
 * impersonation session.
 *
 * Every fact `stop()`/`expired()` need — who started it, which organization,
 * when — is stored explicitly in the session at `start()` time rather than
 * inferred from "whoever `$request->user()` currently resolves to." That
 * makes the lifecycle self-contained and independent of guard-resolution
 * details elsewhere in the stack (Sanctum's stateful guard caches its
 * resolved user for the lifetime of the guard instance, which only matters
 * across multiple requests sharing one process — e.g. under Octane, or a
 * test making several requests in one method — never in a normal one
 * process per request deployment, but there is no reason for this service
 * to depend on that distinction either way).
 */
class ImpersonationService
{
    /** Maximum time an impersonation session may run before it is force-stopped. */
    public const MAX_MINUTES = 60;

    /** Begin impersonating an organization's owner. Returns the impersonated user. */
    public function start(Request $request, User $admin, Team $organization): User
    {
        $owner = $organization->owner;

        abort_if(! $owner, 404);
        abort_if($this->isImpersonating($request), 409, 'Already impersonating.');
        abort_if($owner->is_platform_admin, 403, 'Cannot impersonate another platform admin.');

        AuditLog::record(
            'impersonation.started',
            $owner,
            ['organization_id' => $organization->id, 'owner_id' => $owner->id],
            teamId: $organization->id,
            userId: $admin->id,
        );

        $request->session()->put('impersonator_id', $admin->id);
        $request->session()->put('impersonated_user_id', $owner->id);
        $request->session()->put('impersonating_team_id', $organization->id);
        $request->session()->put('impersonation_started_at', now()->timestamp);
        $request->session()->regenerate();

        auth()->guard('web')->login($owner);

        return $owner;
    }

    public function isImpersonating(Request $request): bool
    {
        return $request->session()->has('impersonator_id');
    }

    /** Whether the current impersonation session has run past its maximum lifetime. */
    public function expired(Request $request): bool
    {
        $startedAt = $request->session()->get('impersonation_started_at');

        return $startedAt !== null && (now()->timestamp - (int) $startedAt) > (self::MAX_MINUTES * 60);
    }

    /**
     * Stop impersonating and restore the original admin's session.
     *
     * Returns the restored admin, or aborts with 403 if there is nothing to
     * restore, or if the original admin no longer exists or is no longer a
     * platform admin — a demoted or deleted admin can never be restored as an
     * admin session.
     */
    public function stop(Request $request, string $action = 'impersonation.stopped'): User
    {
        $impersonatorId = $request->session()->pull('impersonator_id');
        $impersonatedUserId = $request->session()->pull('impersonated_user_id');
        $organizationId = $request->session()->pull('impersonating_team_id');
        $request->session()->forget('impersonation_started_at');

        abort_if(! $impersonatorId, 403);

        $admin = User::find($impersonatorId);

        abort_if(
            ! $admin || ! $admin->is_platform_admin,
            403,
            'The original admin session can no longer be restored.',
        );

        AuditLog::record(
            $action,
            $organizationId ? Team::find($organizationId) : null,
            ['organization_id' => $organizationId, 'owner_id' => $impersonatedUserId],
            teamId: $organizationId,
            userId: $admin->id,
        );

        $request->session()->regenerate();

        auth()->guard('web')->loginUsingId($admin->id);

        return $admin;
    }
}
