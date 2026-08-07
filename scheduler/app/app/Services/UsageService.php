<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\Post;
use App\Models\Team;

/**
 * Computes an organization's current usage and whether it may consume more of
 * a metered resource under its plan.
 */
class UsageService
{
    public function __construct(private readonly PlanService $plans) {}

    public function usage(Team $team, string $key): int
    {
        $workspaceIds = $team->workspaces()->pluck('id');

        return match ($key) {
            'workspaces' => $workspaceIds->count(),
            'channels' => Channel::whereIn('workspace_id', $workspaceIds)->count(),
            // A pending invitation reserves a seat just as much as an
            // accepted one — otherwise an organization could send unlimited
            // invitations and blow past its limit the moment they're all
            // accepted at once.
            'members' => $team->allUsers()->count() + $team->teamInvitations()->count(),
            'monthly_posts' => Post::whereIn('workspace_id', $workspaceIds)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
            default => 0,
        };
    }

    /** Whether the organization can add `$amount` more of `$key`. */
    public function allows(Team $team, string $key, int $amount = 1): bool
    {
        if ($this->plans->isUnlimited($team, $key)) {
            return true;
        }

        return ($this->usage($team, $key) + $amount) <= $this->plans->limit($team, $key);
    }

    public function remaining(Team $team, string $key): int
    {
        if ($this->plans->isUnlimited($team, $key)) {
            return PHP_INT_MAX;
        }

        return max(0, $this->plans->limit($team, $key) - $this->usage($team, $key));
    }

    /**
     * A snapshot of all metered resources for display in the UI.
     *
     * @return array<string, array{usage:int, limit:int}>
     */
    public function snapshot(Team $team): array
    {
        $snapshot = [];

        foreach (array_keys($this->plans->limits($team)) as $key) {
            $snapshot[$key] = [
                'usage' => $this->usage($team, $key),
                'limit' => $this->plans->limit($team, $key),
            ];
        }

        return $snapshot;
    }
}
