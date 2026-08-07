<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Services\ImpersonationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a fixed set of identity-changing, financial, and irreversible
 * account/organization-destroying actions while a platform admin is
 * impersonating a user — per master prompt §8.
 *
 * Impersonation exists so support can reproduce a tenant's day-to-day
 * problem (a stuck post, a misconfigured campaign); it must never become a
 * channel for changing that tenant's login credentials, moving their money,
 * or deleting their account or organization under their own identity.
 * Ordinary content management (campaigns, ideas, media, tags, channels) is
 * deliberately left alone — blocking that too would defeat the point of
 * impersonating someone to fix their account in the first place.
 *
 * Registered globally on the `web` group, like EnforceImpersonationLifetime,
 * rather than wrapped around each route individually: several blocked
 * routes (password, 2FA, passkeys, account deletion) are registered by
 * Fortify/Jetstream in vendor route files this app cannot — and should
 * not — fork just to attach one middleware.
 *
 * A break-glass escape hatch exists for the rare legitimate case (a tenant
 * asks support, on a recorded channel, to change a setting because they are
 * locked out): submitting `break_glass_reason` lets the request through, but
 * writes an `impersonation.break_glass` audit row naming the admin, the
 * impersonated user, the route, and the stated reason — so the override is
 * exceptional and traceable, never silent.
 */
class BlockHighRiskActionsDuringImpersonation
{
    /**
     * Route names blocked while impersonating.
     */
    public const BLOCKED_ROUTES = [
        // Billing — impersonation must never move the tenant's money.
        'billing.checkout',
        'billing.portal',

        // Credentials — logging in as someone is not license to become them.
        'user-password.update',
        'user-profile-information.update',
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.disable',
        'two-factor.regenerate-recovery-codes',
        'passkey.store',
        'passkey.destroy',
        'other-browser-sessions.destroy',

        // Irreversible account/organization destruction.
        'current-user.destroy',
        'teams.destroy',
        'workspaces.destroy',
    ];

    public function __construct(private readonly ImpersonationService $impersonation) {}

    public function handle(Request $request, Closure $next): Response
    {
        $name = $request->route()?->getName();

        if (! $name || ! in_array($name, self::BLOCKED_ROUTES, true) || ! $this->impersonation->isImpersonating($request)) {
            return $next($request);
        }

        $reason = trim((string) $request->input('break_glass_reason'));

        if ($reason === '') {
            abort(
                403,
                'This action is blocked while impersonating a user. If the tenant has explicitly '
                    .'asked for this on a supported channel, resubmit with a documented reason.',
            );
        }

        AuditLog::record(
            'impersonation.break_glass',
            null,
            [
                'route' => $name,
                'reason' => $reason,
                'organization_id' => $request->session()->get('impersonating_team_id'),
                'owner_id' => $request->session()->get('impersonated_user_id'),
            ],
            teamId: $request->session()->get('impersonating_team_id'),
            userId: $request->session()->get('impersonator_id'),
        );

        return $next($request);
    }
}
