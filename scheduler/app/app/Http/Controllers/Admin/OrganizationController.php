<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Team;
use App\Services\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super-admin control plane: manage every organization on the platform.
 */
class OrganizationController extends Controller
{
    public function __construct(private readonly ImpersonationService $impersonation) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Organizations', [
            'organizations' => Team::query()
                ->withCount('workspaces')
                ->with('owner:id,name,email')
                ->latest()
                ->limit(500)
                ->get()
                ->map(fn (Team $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'plan' => $t->plan,
                    'owner' => $t->owner?->only('name', 'email'),
                    'workspaces' => $t->workspaces_count,
                    'suspended' => $t->isSuspended(),
                    'subscribed' => $t->subscribed('default'),
                ]),
            'stats' => [
                'organizations' => Team::count(),
                'subscribed' => Team::whereHas('subscriptions', fn ($q) => $q->where('stripe_status', 'active'))->count(),
            ],
        ]);
    }

    public function suspend(Request $request, Team $organization): RedirectResponse
    {
        $wasSuspended = $organization->isSuspended();

        $organization->update(['suspended_at' => $wasSuspended ? null : now()]);

        AuditLog::record(
            $wasSuspended ? 'organization.reactivated' : 'organization.suspended',
            $organization,
            ['before' => ['suspended' => $wasSuspended], 'after' => ['suspended' => ! $wasSuspended]],
            teamId: $organization->id,
        );

        return back()->with('status', 'organization-toggled');
    }

    /**
     * Impersonate the organization owner for support.
     *
     * Requires a recently-confirmed password (`password.confirm` on the
     * route). Nested impersonation and impersonating another platform admin
     * are rejected; start/stop are audited and the session is regenerated —
     * see `ImpersonationService`.
     */
    public function impersonate(Request $request, Team $organization): RedirectResponse
    {
        $this->impersonation->start($request, $request->user(), $organization);

        return redirect()->route('dashboard')->with('status', 'impersonating');
    }

    public function stopImpersonating(Request $request): RedirectResponse
    {
        $this->impersonation->stop($request);

        return redirect()->route('admin.organizations.index');
    }
}
