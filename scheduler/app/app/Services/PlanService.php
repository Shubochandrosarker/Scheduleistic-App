<?php

namespace App\Services;

use App\Models\Team;

/**
 * Reads plan definitions and resolves the limits for an organization's plan.
 */
class PlanService
{
    public const UNLIMITED = -1;

    /** @return array<string, mixed> */
    public function plan(Team $team): array
    {
        $plans = config('plans');

        return $plans[$team->plan] ?? $plans['free'];
    }

    /** @return array<string, int> */
    public function limits(Team $team): array
    {
        return $this->plan($team)['limits'] ?? [];
    }

    public function limit(Team $team, string $key): int
    {
        return $this->limits($team)[$key] ?? self::UNLIMITED;
    }

    public function isUnlimited(Team $team, string $key): bool
    {
        return $this->limit($team, $key) === self::UNLIMITED;
    }
}
