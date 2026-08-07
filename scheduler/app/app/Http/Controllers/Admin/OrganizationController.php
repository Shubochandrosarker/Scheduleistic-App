<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrganizationIndexRequest;
use App\Models\AuditLog;
use App\Models\Team;
use App\Services\Admin\AdminOrganizationQuery;
use App\Services\ImpersonationService;
use App\Services\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super-admin control plane: manage every organization on the platform.
 */
class OrganizationController extends Controller
{
    public function __construct(
        private readonly ImpersonationService $impersonation,
        private readonly AdminOrganizationQuery $query,
        private readonly PlanService $plans,
    ) {}

    public function index(OrganizationIndexRequest $request): Response
    {
        $organizations = $this->query->paginate($request);

        return Inertia::render('Admin/Organizations', [
            'organizations' => $organizations->through(fn (Team $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'base_plan' => $this->plans->basePlanKey($t),
                'effective_plan' => $this->plans->planKey($t),
                'has_override' => $this->plans->hasActiveOverride($t),
                'owner' => $t->owner?->only(['id', 'name', 'email']),
                'workspaces' => $t->workspaces_count,
                'suspended' => $t->isSuspended(),
                'subscribed' => $t->subscribed('default'),
                'created_at' => $t->created_at,
            ]),
            'filters' => $request->validated(),
            'planOptions' => collect(config('plans'))->map(fn ($p, $key) => ['key' => $key, 'name' => $p['name']])->values(),
            'stats' => [
                'organizations' => Team::count(),
                'subscribed' => Team::whereHas('subscriptions', fn ($q) => $q->where('stripe_status', 'active'))->count(),
                'suspended' => Team::whereNotNull('suspended_at')->count(),
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
