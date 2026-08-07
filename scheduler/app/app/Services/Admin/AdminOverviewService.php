<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use App\Services\PlanService;
use Illuminate\Support\Collection;
use Laravel\Cashier\Subscription;

/**
 * Computes the platform admin overview: totals, subscription health, and
 * recent activity.
 *
 * Every query here is bounded and reads only local state — no remote Stripe
 * API calls happen while rendering this page (a deliberate per-organization
 * resync action lives on the organization detail page instead, for whoever
 * needs live truth for one specific tenant).
 */
class AdminOverviewService
{
    public function __construct(private readonly PlanService $plans) {}

    /** @return array<string, mixed> */
    public function stats(): array
    {
        return [
            'users' => [
                'total' => User::count(),
                'suspended' => User::whereNotNull('suspended_at')->count(),
                'platform_admins' => User::where('is_platform_admin', true)->count(),
            ],
            'organizations' => [
                'total' => Team::count(),
                'suspended' => Team::whereNotNull('suspended_at')->count(),
                'per_plan' => $this->organizationsPerEffectivePlan(),
                'with_active_override' => $this->organizationsWithActiveOverride(),
            ],
            'subscriptions' => $this->subscriptionBreakdown(),
        ];
    }

    /**
     * Local Cashier `subscriptions` rows only, grouped by status — never a
     * remote Stripe call, and one query rather than one per organization.
     *
     * @return array<string, int>
     */
    protected function subscriptionBreakdown(): array
    {
        return Subscription::query()
            ->selectRaw('stripe_status, count(*) as aggregate')
            ->groupBy('stripe_status')
            ->pluck('aggregate', 'stripe_status')
            ->all();
    }

    /**
     * Every organization's effective plan key. `PlanService::planKey()` is a
     * pure config/array lookup against already-selected columns — no query
     * per row — so resolving it per organization here is cheap; chunking
     * just keeps memory bounded on a very large table.
     *
     * @return array<string, int>
     */
    protected function organizationsPerEffectivePlan(): array
    {
        $counts = [];

        Team::query()
            ->select(['id', 'plan', 'plan_override', 'plan_override_expires_at'])
            ->orderBy('id')
            ->chunk(500, function (Collection $teams) use (&$counts) {
                foreach ($teams as $team) {
                    $key = $this->plans->planKey($team);
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                }
            });

        return $counts;
    }

    protected function organizationsWithActiveOverride(): int
    {
        return Team::query()
            ->whereNotNull('plan_override')
            ->where(fn ($q) => $q->whereNull('plan_override_expires_at')
                ->orWhere('plan_override_expires_at', '>', now()))
            ->count();
    }

    /** @return Collection<int, User> */
    public function recentSignups(int $limit = 10): Collection
    {
        return User::query()
            ->latest()
            ->limit($limit)
            ->get(['id', 'name', 'email', 'created_at']);
    }

    /** @return Collection<int, AuditLog> */
    public function recentAuditEvents(int $limit = 20): Collection
    {
        return AuditLog::query()
            ->with(['user:id,name,email', 'team:id,name'])
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Deliberate, human-readable warnings — not raw counts the admin has to
     * interpret. Empty when there is nothing to flag.
     *
     * @return array<int, array{type: string, message: string, count: int}>
     */
    public function warnings(): array
    {
        $warnings = [];

        $pastDue = Subscription::where('stripe_status', 'past_due')->count();
        if ($pastDue > 0) {
            $warnings[] = [
                'type' => 'past_due',
                'message' => $pastDue === 1
                    ? '1 subscription is past due'
                    : "{$pastDue} subscriptions are past due",
                'count' => $pastDue,
            ];
        }

        $suspendedOrgs = Team::whereNotNull('suspended_at')->count();
        if ($suspendedOrgs > 0) {
            $warnings[] = [
                'type' => 'suspended_organizations',
                'message' => $suspendedOrgs === 1
                    ? '1 organization is suspended'
                    : "{$suspendedOrgs} organizations are suspended",
                'count' => $suspendedOrgs,
            ];
        }

        $suspendedUsers = User::whereNotNull('suspended_at')->count();
        if ($suspendedUsers > 0) {
            $warnings[] = [
                'type' => 'suspended_users',
                'message' => $suspendedUsers === 1
                    ? '1 user account is suspended'
                    : "{$suspendedUsers} user accounts are suspended",
                'count' => $suspendedUsers,
            ];
        }

        return $warnings;
    }
}
