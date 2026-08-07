<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EntitlementClearRequest;
use App\Http\Requests\Admin\EntitlementGrantRequest;
use App\Http\Requests\Admin\GrantPlanOverrideRequest;
use App\Http\Requests\Admin\RemovePlanOverrideRequest;
use App\Models\AuditLog;
use App\Models\Team;
use App\Services\Admin\EntitlementOverrideService;
use App\Services\Admin\PlanOverrideService;
use App\Services\PlanService;
use App\Services\UsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Organization detail: profile, members, workspaces, subscription,
 * effective plan/entitlements, branding, and audit history for one tenant.
 *
 * Mutations here and the owner-facing equivalents (billing, branding) all
 * go through the same underlying services (PlanService, UsageService) and
 * `teams` columns — this controller only adds the platform-admin authority
 * to reach into another organization's plan override and entitlements,
 * which no owner-facing route exposes.
 */
class OrganizationDetailController extends Controller
{
    public function __construct(
        private readonly PlanService $plans,
        private readonly UsageService $usage,
        private readonly PlanOverrideService $planOverrides,
        private readonly EntitlementOverrideService $entitlements,
    ) {}

    public function show(Team $organization): Response
    {
        $organization->loadCount('workspaces');
        $organization->load(['owner:id,name,email,created_at', 'users:id,name,email']);

        return Inertia::render('Admin/Organizations/Show', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'timezone' => $organization->timezone,
                'suspended' => $organization->isSuspended(),
                'suspended_at' => $organization->suspended_at,
                'created_at' => $organization->created_at,
                'onboarded_at' => $organization->onboarded_at,
                'workspaces_count' => $organization->workspaces_count,
            ],
            'owner' => $organization->owner?->only(['id', 'name', 'email', 'created_at']),
            'members' => $organization->users->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->membership->role,
            ]),
            'workspaces' => $organization->workspaces()->withCount('channels')->get(['id', 'name', 'team_id'])
                ->map(fn ($w) => ['id' => $w->id, 'name' => $w->name, 'channels_count' => $w->channels_count]),
            'subscription' => $this->subscriptionSummary($organization),
            'plan' => [
                'base' => $this->plans->basePlanKey($organization),
                'effective' => $this->plans->planKey($organization),
                'has_active_override' => $this->plans->hasActiveOverride($organization),
                'override' => [
                    'plan' => $organization->plan_override,
                    'expires_at' => $organization->plan_override_expires_at,
                    'reason' => $organization->plan_override_reason,
                    'set_by' => $organization->planOverrideSetBy?->only(['id', 'name']),
                ],
                'capabilities' => $this->plans->capabilities($organization),
                'limits' => $this->plans->limits($organization),
                'entitlement_overrides' => $organization->entitlements ?? [],
            ],
            'usage' => $this->usage->snapshot($organization),
            'branding' => [
                'config' => $organization->brandingConfig(),
                'custom_domain' => $organization->custom_domain,
                'custom_domain_verified' => $organization->customDomainVerified(),
            ],
            'planOptions' => collect(config('plans'))->map(fn ($p, $key) => ['key' => $key, 'name' => $p['name']])->values(),
            'auditHistory' => AuditLog::where('team_id', $organization->id)
                ->with('user:id,name,email')
                ->latest('created_at')
                ->paginate(20)
                ->through(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'actor' => $log->user?->only(['id', 'name', 'email']),
                    'context' => $log->context,
                    'created_at' => $log->created_at,
                ]),
        ]);
    }

    public function updatePlanOverride(GrantPlanOverrideRequest $request, Team $organization): RedirectResponse
    {
        $this->planOverrides->grant(
            $organization,
            $request->user(),
            $request->validated('plan'),
            $request->validated('reason'),
            $request->validated('expires_at') ? Carbon::parse($request->validated('expires_at')) : null,
        );

        return back()->with('status', 'plan-override-granted');
    }

    public function removePlanOverride(RemovePlanOverrideRequest $request, Team $organization): RedirectResponse
    {
        $this->planOverrides->remove($organization, $request->user(), $request->validated('reason'));

        return back()->with('status', 'plan-override-removed');
    }

    /** Reconcile the base plan from the local Cashier subscription — a deliberate action, not automatic. */
    public function resyncPlan(Request $request, Team $organization): RedirectResponse
    {
        $before = $organization->plan;
        $organization->syncPlanFromSubscription();

        AuditLog::record('organization.plan_resynced', $organization, [
            'before' => $before,
            'after' => $organization->fresh()->plan,
        ], teamId: $organization->id);

        return back()->with('status', 'plan-resynced');
    }

    public function grantEntitlement(EntitlementGrantRequest $request, Team $organization): RedirectResponse
    {
        $admin = $request->user();
        $reason = $request->validated('reason');

        if ($request->validated('type') === 'limit') {
            $this->entitlements->raiseLimit($organization, $admin, $request->validated('key'), (int) $request->validated('value'), $reason);
        } else {
            $this->entitlements->grantCapability($organization, $admin, $request->validated('key'), $reason);
        }

        return back()->with('status', 'entitlement-granted');
    }

    public function clearEntitlement(EntitlementClearRequest $request, Team $organization): RedirectResponse
    {
        $admin = $request->user();
        $reason = $request->validated('reason');

        if ($request->validated('type') === 'limit') {
            $this->entitlements->clearLimit($organization, $admin, $request->validated('key'), $reason);
        } else {
            $this->entitlements->clearCapability($organization, $admin, $request->validated('key'), $reason);
        }

        return back()->with('status', 'entitlement-cleared');
    }

    /**
     * Non-secret billing metadata only: Stripe IDs, status, price ID mapped
     * to a configured plan when known, trial/period state — all from the
     * local Cashier `subscriptions` row, never a live Stripe API call.
     *
     * @return array<string, mixed>|null
     */
    protected function subscriptionSummary(Team $organization): ?array
    {
        $subscription = $organization->subscription('default');

        if (! $subscription) {
            return null;
        }

        $matchedPlan = collect(config('plans'))
            ->first(fn ($p) => ! empty($p['price_id']) && $p['price_id'] === $subscription->stripe_price);

        return [
            'stripe_customer_id' => $organization->stripe_id,
            'stripe_subscription_id' => $subscription->stripe_id,
            'status' => $subscription->stripe_status,
            'price_id' => $subscription->stripe_price,
            'matched_plan' => $matchedPlan['name'] ?? null,
            'quantity' => $subscription->quantity,
            'trial_ends_at' => $subscription->trial_ends_at,
            'ends_at' => $subscription->ends_at,
            'on_grace_period' => $subscription->onGracePeriod(),
            'canceled' => $subscription->canceled(),
            'updated_at' => $subscription->updated_at,
        ];
    }
}
