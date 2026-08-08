<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Grants, changes, or removes a platform-admin plan override on an
 * organization. `PlanService` is the only thing that ever *reads* an
 * override to decide access; this is the only thing that ever *writes* one.
 *
 * Every mutation is transactional and produces an append-only audit event
 * with before/after values and the admin-supplied reason — never the base
 * `teams.plan` column, which stays exclusively under
 * `Team::syncPlanFromSubscription()`'s control.
 */
class PlanOverrideService
{
    /** @throws InvalidArgumentException if $planKey is not a configured plan. */
    public function grant(Team $team, User $admin, string $planKey, string $reason, ?Carbon $expiresAt = null): Team
    {
        if (! isset(config('plans')[$planKey])) {
            throw new InvalidArgumentException("Unknown plan [{$planKey}].");
        }

        $before = $this->snapshot($team);

        DB::transaction(function () use ($team, $admin, $planKey, $reason, $expiresAt) {
            $team->forceFill([
                'plan_override' => $planKey,
                'plan_override_expires_at' => $expiresAt,
                'plan_override_reason' => $reason,
                'plan_override_set_by' => $admin->id,
            ])->save();
        });

        $team->refresh();

        AuditLog::record('organization.plan_override_granted', $team, [
            'before' => $before,
            'after' => $this->snapshot($team),
            'reason' => $reason,
        ], teamId: $team->id, userId: $admin->id);

        return $team;
    }

    public function remove(Team $team, User $admin, string $reason): Team
    {
        $before = $this->snapshot($team);

        DB::transaction(function () use ($team) {
            $team->forceFill([
                'plan_override' => null,
                'plan_override_expires_at' => null,
                'plan_override_reason' => null,
                'plan_override_set_by' => null,
            ])->save();
        });

        $team->refresh();

        AuditLog::record('organization.plan_override_removed', $team, [
            'before' => $before,
            'after' => $this->snapshot($team),
            'reason' => $reason,
        ], teamId: $team->id, userId: $admin->id);

        return $team;
    }

    /** @return array<string, mixed> */
    protected function snapshot(Team $team): array
    {
        return [
            'plan_override' => $team->plan_override,
            'plan_override_expires_at' => $team->plan_override_expires_at?->toIso8601String(),
            'plan_override_reason' => $team->plan_override_reason,
        ];
    }
}
