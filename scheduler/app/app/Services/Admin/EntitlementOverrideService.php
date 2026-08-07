<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;

/**
 * Grants or clears a per-organization entitlement override on top of
 * `teams.entitlements`.
 *
 * This never lowers a limit or revokes a capability the plan already
 * grants — `PlanService::capabilities()`/`limits()` already enforce that at
 * read time regardless of what is stored here (a capability override is
 * only ever applied when true; a limit override only ever raises via
 * `maxLimit()`), so this service does not need to re-validate direction.
 * "Clear" removes the override key entirely, reverting to whatever the
 * plan itself defines — it is not the same operation as "revoke."
 */
class EntitlementOverrideService
{
    public function grantCapability(Team $team, User $admin, string $capability, string $reason): Team
    {
        return $this->mutate($team, $admin, 'organization.entitlement_granted', $reason, function (array $entitlements) use ($capability) {
            $entitlements['capabilities'][$capability] = true;

            return $entitlements;
        }, ['capability' => $capability]);
    }

    public function clearCapability(Team $team, User $admin, string $capability, string $reason): Team
    {
        return $this->mutate($team, $admin, 'organization.entitlement_cleared', $reason, function (array $entitlements) use ($capability) {
            unset($entitlements['capabilities'][$capability]);

            return $entitlements;
        }, ['capability' => $capability]);
    }

    public function raiseLimit(Team $team, User $admin, string $limitKey, int $value, string $reason): Team
    {
        return $this->mutate($team, $admin, 'organization.entitlement_limit_raised', $reason, function (array $entitlements) use ($limitKey, $value) {
            $entitlements['limits'][$limitKey] = $value;

            return $entitlements;
        }, ['limit' => $limitKey, 'value' => $value]);
    }

    public function clearLimit(Team $team, User $admin, string $limitKey, string $reason): Team
    {
        return $this->mutate($team, $admin, 'organization.entitlement_limit_cleared', $reason, function (array $entitlements) use ($limitKey) {
            unset($entitlements['limits'][$limitKey]);

            return $entitlements;
        }, ['limit' => $limitKey]);
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $mutator
     * @param  array<string, mixed>  $extraContext
     */
    protected function mutate(Team $team, User $admin, string $action, string $reason, callable $mutator, array $extraContext): Team
    {
        $before = $team->entitlements ?? [];
        $after = $mutator($before);

        $team->forceFill(['entitlements' => $after])->save();
        $team->refresh();

        AuditLog::record($action, $team, [
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            ...$extraContext,
        ], teamId: $team->id, userId: $admin->id);

        return $team;
    }
}
