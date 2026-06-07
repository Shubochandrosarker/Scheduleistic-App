<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks feature access for organizations a platform admin has suspended
 * (e.g. for non-payment or abuse). Billing routes remain reachable so the
 * owner can resolve the issue, and platform admins are never blocked.
 */
class EnsureOrganizationActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $team = $user?->currentTeam;

        if ($team && $team->isSuspended() && ! $user->is_platform_admin) {
            abort(403, 'This organization has been suspended. Please contact support or update your billing.');
        }

        return $next($request);
    }
}
