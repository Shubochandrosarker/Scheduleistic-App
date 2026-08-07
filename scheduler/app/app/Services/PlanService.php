<?php

namespace App\Services;

use App\Models\Team;

/**
 * Resolves what an organization is entitled to.
 *
 * Entitlements come from three layers, applied in order:
 *
 *  1. The plan definition in config/plans.php (limits + capabilities).
 *  2. Per-organization overrides stored on `teams.entitlements` — used to
 *     grandfather legacy customers and to configure Enterprise accounts.
 *     Overrides only ever *raise* a limit or *grant* a capability, so a plan
 *     change can never silently strip something a customer already had.
 *  3. Nothing else. No caller compares plan names; everything asks can().
 */
class PlanService
{
    public const UNLIMITED = -1;

    /**
     * Legacy boolean flag names kept working for pre-2.0 call sites.
     *
     * @var array<string, string>
     */
    protected const LEGACY_FEATURE_MAP = [
        'client_approval' => 'approvals',
        'white_label' => 'white_label',
        'ai_captions' => 'ai_captions',
        'ai_agents' => 'ai_agents',
    ];

    /** @return array<string, mixed> */
    public function plan(Team $team): array
    {
        $plans = config('plans');

        return $plans[$team->plan] ?? $plans['free'];
    }

    /** The plan key the organization is on, falling back to free. */
    public function planKey(Team $team): string
    {
        return isset(config('plans')[$team->plan]) ? (string) $team->plan : 'free';
    }

    // --- Limits ---------------------------------------------------------

    /** @return array<string, int> */
    public function limits(Team $team): array
    {
        $limits = $this->plan($team)['limits'] ?? [];
        $overrides = $this->overrides($team)['limits'] ?? [];

        foreach ($overrides as $key => $value) {
            if (! is_numeric($value)) {
                continue;
            }

            $limits[$key] = $this->maxLimit($limits[$key] ?? 0, (int) $value);
        }

        return $limits;
    }

    public function limit(Team $team, string $key): int
    {
        return $this->limits($team)[$key] ?? self::UNLIMITED;
    }

    public function isUnlimited(Team $team, string $key): bool
    {
        return $this->limit($team, $key) === self::UNLIMITED;
    }

    /** Unlimited (-1) always wins over any finite value. */
    protected function maxLimit(int $a, int $b): int
    {
        if ($a === self::UNLIMITED || $b === self::UNLIMITED) {
            return self::UNLIMITED;
        }

        return max($a, $b);
    }

    // --- Capabilities ---------------------------------------------------

    /**
     * Every capability key with its resolved boolean value. Shared with the
     * frontend so Vue never hard-codes a plan check.
     *
     * @return array<string, bool>
     */
    public function capabilities(Team $team): array
    {
        $capabilities = $this->plan($team)['capabilities'] ?? [];
        $granted = $this->overrides($team)['capabilities'] ?? [];

        foreach ($granted as $key => $value) {
            // Overrides grant, never revoke.
            if ($value) {
                $capabilities[$key] = true;
            }
        }

        return array_map(static fn ($v) => (bool) $v, $capabilities);
    }

    /** Whether the organization's entitlements include a capability. */
    public function can(Team $team, string $capability): bool
    {
        return $this->capabilities($team)[$capability] ?? false;
    }

    /**
     * The plans that would grant a capability, cheapest first — so the UI can
     * say "Campaigns are on Pro and above" instead of silently disabling it.
     *
     * @return array<int, string> plan keys
     */
    public function plansGranting(string $capability): array
    {
        return collect(config('plans'))
            ->filter(fn (array $plan) => (bool) ($plan['capabilities'][$capability] ?? false))
            ->keys()
            ->all();
    }

    /** The cheapest plan name that grants a capability, or null if none do. */
    public function upgradePlanFor(string $capability): ?string
    {
        $key = $this->plansGranting($capability)[0] ?? null;

        return $key ? (config("plans.{$key}.name") ?? $key) : null;
    }

    // --- Legacy surface -------------------------------------------------

    /**
     * Legacy boolean feature map for a team, including its entitlement
     * overrides. Derived from capabilities so there is one source of truth.
     *
     * @return array<string, mixed>
     */
    public function features(Team $team): array
    {
        return $this->legacyFeatureMap($this->capabilities($team));
    }

    /**
     * The same legacy boolean feature map, but for a plan's raw catalog
     * definition rather than a specific team — no entitlement overrides
     * applied. For describing what a plan offers in the abstract, e.g. a
     * pricing/comparison table showing every plan side by side.
     *
     * @return array<string, mixed>
     */
    public function planFeatures(string $planKey): array
    {
        $capabilities = config("plans.{$planKey}.capabilities") ?? config('plans.free.capabilities', []);

        return $this->legacyFeatureMap($capabilities);
    }

    /** @param array<string, bool> $capabilities
     * @return array<string, mixed>
     */
    protected function legacyFeatureMap(array $capabilities): array
    {
        $features = [];

        foreach (self::LEGACY_FEATURE_MAP as $legacy => $capability) {
            $features[$legacy] = (bool) ($capabilities[$capability] ?? false);
        }

        $features['analytics'] = match (true) {
            ! empty($capabilities['analytics_advanced']) => 'advanced',
            ! empty($capabilities['analytics_basic']) => 'basic',
            default => 'none',
        };

        return $features;
    }

    /**
     * Whether the team's plan grants a flag. Accepts both legacy feature
     * names and canonical capability keys.
     */
    public function feature(Team $team, string $key): bool
    {
        $capability = self::LEGACY_FEATURE_MAP[$key] ?? $key;

        return $this->can($team, $capability);
    }

    // --- Overrides ------------------------------------------------------

    /**
     * Per-organization entitlement overrides.
     *
     * @return array{limits?: array<string,int>, capabilities?: array<string,bool>}
     */
    protected function overrides(Team $team): array
    {
        $overrides = $team->entitlements;

        return is_array($overrides) ? $overrides : [];
    }
}
