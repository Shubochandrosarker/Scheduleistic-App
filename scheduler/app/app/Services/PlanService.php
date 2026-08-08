<?php

namespace App\Services;

use App\Models\Team;

/**
 * Resolves what an organization is entitled to.
 *
 * Entitlements come from these layers, applied in order:
 *
 *  1. The *base* plan — `teams.plan`, synchronized from the live Cashier
 *     subscription by `Team::syncPlanFromSubscription()`. This is Stripe's
 *     truth and nothing here ever writes to it.
 *  2. A platform-admin *plan override* (`teams.plan_override` +
 *     `plan_override_expires_at`) layered on top: a valid, non-expired
 *     override wins over the base plan entirely. This is the one and only
 *     place that decides the *effective* plan — `plan()`/`planKey()` — so no
 *     other call site ever needs to know an override exists. An expired or
 *     unknown override stops affecting access automatically, the moment
 *     something asks; nothing depends on a scheduled cleanup job.
 *  3. Per-organization entitlement overrides stored on `teams.entitlements`
 *     — used to grandfather legacy customers and to configure Enterprise
 *     accounts. These only ever *raise* a limit or *grant* a capability, so
 *     a plan change can never silently strip something a customer already
 *     had.
 *  4. Nothing else. No caller compares plan names; everything asks can().
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

    // --- Effective plan ---------------------------------------------------

    /** The organization's effective plan definition — override if active, else base. */
    public function plan(Team $team): array
    {
        return config('plans')[$this->planKey($team)];
    }

    /** The effective plan key: a valid, non-expired override wins over the base plan. */
    public function planKey(Team $team): string
    {
        if ($this->hasActiveOverride($team)) {
            return (string) $team->plan_override;
        }

        return $this->basePlanKey($team);
    }

    /** The Stripe-synchronized base plan key, ignoring any admin override. */
    public function basePlanKey(Team $team): string
    {
        return isset(config('plans')[$team->plan]) ? (string) $team->plan : 'free';
    }

    /**
     * Whether the organization currently has a live plan override — set,
     * pointing at a plan that still exists, and not expired. An override
     * whose plan was later removed from the catalog, or whose expiry has
     * passed, is simply inert: it is never deleted for you, it just stops
     * being effective the instant this is asked.
     */
    public function hasActiveOverride(Team $team): bool
    {
        if (! $team->plan_override || ! isset(config('plans')[$team->plan_override])) {
            return false;
        }

        return ! $team->plan_override_expires_at || $team->plan_override_expires_at->isFuture();
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
