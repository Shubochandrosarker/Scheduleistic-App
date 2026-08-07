<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks feature access for an individual account a platform admin has
 * suspended — e.g. for abuse — distinct from `EnsureOrganizationActive`,
 * which suspends an entire organization rather than one person.
 *
 * Unlike organization suspension, this has no exemption for platform admins:
 * suspending a specific account is meant to be absolute regardless of that
 * account's other privileges. Logout stays reachable because Fortify's
 * logout route is registered outside the route group this runs in.
 */
class EnsureUserActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isSuspended()) {
            abort(403, 'This account has been suspended. Please contact support.');
        }

        return $next($request);
    }
}
