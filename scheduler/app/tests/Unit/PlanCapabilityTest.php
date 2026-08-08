<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Services\PlanService;
use Tests\TestCase;

/**
 * PlanService is the only thing in the app allowed to decide what an
 * organization is entitled to, so it is tested directly rather than only
 * through the HTTP layer.
 */
class PlanCapabilityTest extends TestCase
{
    protected function plans(): PlanService
    {
        return app(PlanService::class);
    }

    /** An unsaved model is enough: nothing here touches the database. */
    protected function team(string $plan, ?array $entitlements = null): Team
    {
        $team = new Team;
        $team->plan = $plan;
        $team->entitlements = $entitlements;

        return $team;
    }

    public function test_every_plan_declares_every_capability(): void
    {
        $keys = array_keys(config('plans.enterprise.capabilities'));

        foreach (config('plans') as $planKey => $plan) {
            foreach ($keys as $capability) {
                $this->assertArrayHasKey(
                    $capability,
                    $plan['capabilities'],
                    "Plan [{$planKey}] is missing capability [{$capability}]."
                );
            }
        }
    }

    public function test_free_plan_grants_no_paid_capabilities(): void
    {
        $team = $this->team('free');

        $this->assertFalse($this->plans()->can($team, 'media_library'));
        $this->assertFalse($this->plans()->can($team, 'campaigns'));
        $this->assertFalse($this->plans()->can($team, 'white_label'));
    }

    public function test_capabilities_increase_with_the_plan_tier(): void
    {
        $this->assertFalse($this->plans()->can($this->team('solo'), 'media_library'));
        $this->assertTrue($this->plans()->can($this->team('pro'), 'media_library'));
        $this->assertTrue($this->plans()->can($this->team('agency'), 'white_label'));
        $this->assertTrue($this->plans()->can($this->team('scale'), 'competitor_tracking'));
    }

    public function test_enterprise_grants_everything_and_unlimited_limits(): void
    {
        $team = $this->team('enterprise');

        foreach ($this->plans()->capabilities($team) as $capability => $granted) {
            $this->assertTrue($granted, "Enterprise should grant [{$capability}].");
        }

        $this->assertTrue($this->plans()->isUnlimited($team, 'workspaces'));
        $this->assertTrue($this->plans()->isUnlimited($team, 'storage_mb'));
    }

    public function test_an_unknown_plan_falls_back_to_free(): void
    {
        $team = $this->team('a-plan-that-was-deleted');

        $this->assertSame('free', $this->plans()->planKey($team));
        $this->assertSame(config('plans.free.limits.workspaces'), $this->plans()->limit($team, 'workspaces'));
    }

    // --- Grandfathering ---------------------------------------------------

    public function test_an_override_grants_a_capability_the_plan_does_not(): void
    {
        $team = $this->team('free', ['capabilities' => ['white_label' => true]]);

        $this->assertTrue($this->plans()->can($team, 'white_label'));
        // Untouched capabilities stay off — an override is not a blanket unlock.
        $this->assertFalse($this->plans()->can($team, 'media_library'));
    }

    public function test_an_override_cannot_revoke_a_capability_the_plan_grants(): void
    {
        $team = $this->team('agency', ['capabilities' => ['white_label' => false]]);

        $this->assertTrue(
            $this->plans()->can($team, 'white_label'),
            'Overrides must only ever grant, never revoke.'
        );
    }

    public function test_an_override_raises_a_limit_but_never_lowers_one(): void
    {
        $raised = $this->team('pro', ['limits' => ['workspaces' => 99]]);
        $this->assertSame(99, $this->plans()->limit($raised, 'workspaces'));

        $lowered = $this->team('pro', ['limits' => ['workspaces' => 1]]);
        $this->assertSame(
            config('plans.pro.limits.workspaces'),
            $this->plans()->limit($lowered, 'workspaces'),
            'A lower override must not reduce the plan limit.'
        );
    }

    public function test_unlimited_beats_any_finite_override(): void
    {
        $team = $this->team('enterprise', ['limits' => ['workspaces' => 5]]);

        $this->assertTrue($this->plans()->isUnlimited($team, 'workspaces'));
    }

    // --- Legacy surface ---------------------------------------------------

    public function test_legacy_feature_names_still_resolve(): void
    {
        $agency = $this->team('agency');

        $this->assertTrue($this->plans()->feature($agency, 'client_approval'));
        $this->assertTrue($this->plans()->feature($agency, 'white_label'));
        $this->assertTrue($this->plans()->feature($agency, 'ai_agents'));
        $this->assertFalse($this->plans()->feature($agency, 'not_a_real_flag'));
    }

    public function test_legacy_analytics_tier_is_derived_from_capabilities(): void
    {
        $this->assertSame('none', $this->plans()->features($this->team('free'))['analytics']);
        $this->assertSame('basic', $this->plans()->features($this->team('pro'))['analytics']);
        $this->assertSame('advanced', $this->plans()->features($this->team('scale'))['analytics']);
    }

    public function test_upgrade_target_names_the_cheapest_plan_that_grants_it(): void
    {
        $this->assertSame('Pro', $this->plans()->upgradePlanFor('media_library'));
        $this->assertSame('Agency', $this->plans()->upgradePlanFor('white_label'));
        $this->assertSame('Solo', $this->plans()->upgradePlanFor('ai_captions'));
    }

    /**
     * The billing comparison table describes each plan's own catalog
     * definition, not the viewing team's entitlement overrides — this is
     * what `planFeatures()` exists for, as distinct from `features(Team)`.
     */
    public function test_plan_features_reflects_the_plans_own_capabilities_not_a_teams_overrides(): void
    {
        $this->assertFalse($this->plans()->planFeatures('free')['ai_captions']);
        $this->assertTrue($this->plans()->planFeatures('solo')['ai_captions']);
        $this->assertTrue($this->plans()->planFeatures('agency')['white_label']);
        $this->assertSame('advanced', $this->plans()->planFeatures('scale')['analytics']);
    }

    public function test_plan_features_falls_back_to_free_for_an_unknown_plan(): void
    {
        $this->assertSame(
            $this->plans()->planFeatures('free'),
            $this->plans()->planFeatures('a-plan-that-was-deleted'),
        );
    }

    // --- Effective plan (admin override layer) -----------------------------

    public function test_a_valid_non_expired_override_wins_over_the_base_plan(): void
    {
        $team = $this->team('free');
        $team->plan_override = 'scale';

        $this->assertTrue($this->plans()->hasActiveOverride($team));
        $this->assertSame('scale', $this->plans()->planKey($team));
        $this->assertSame('free', $this->plans()->basePlanKey($team));
        $this->assertTrue($this->plans()->can($team, 'competitor_tracking'));
    }

    public function test_an_expired_override_no_longer_affects_access(): void
    {
        $team = $this->team('free');
        $team->plan_override = 'scale';
        $team->plan_override_expires_at = now()->subDay();

        $this->assertFalse($this->plans()->hasActiveOverride($team));
        $this->assertSame('free', $this->plans()->planKey($team));
        $this->assertFalse($this->plans()->can($team, 'competitor_tracking'));
    }

    public function test_a_permanent_override_has_no_expiry_to_check(): void
    {
        $team = $this->team('free');
        $team->plan_override = 'agency';
        $team->plan_override_expires_at = null;

        $this->assertTrue($this->plans()->hasActiveOverride($team));
        $this->assertSame('agency', $this->plans()->planKey($team));
    }

    public function test_an_override_pointing_at_an_unknown_plan_is_rejected(): void
    {
        $team = $this->team('free');
        $team->plan_override = 'a-plan-that-was-deleted';

        $this->assertFalse($this->plans()->hasActiveOverride($team));
        $this->assertSame('free', $this->plans()->planKey($team));
    }

    public function test_removing_the_override_restores_the_base_plan(): void
    {
        $team = $this->team('pro');
        $team->plan_override = 'scale';
        $this->assertSame('scale', $this->plans()->planKey($team));

        $team->plan_override = null;

        $this->assertFalse($this->plans()->hasActiveOverride($team));
        $this->assertSame('pro', $this->plans()->planKey($team));
    }

    public function test_capability_and_limit_resolution_go_through_the_effective_plan(): void
    {
        $team = $this->team('free');
        $team->plan_override = 'agency';

        // capabilities()/limits()/can() all resolve through plan()/planKey()
        // internally, so the override applies everywhere without a separate
        // check at each call site.
        $this->assertTrue($this->plans()->can($team, 'white_label'));
        $this->assertSame(config('plans.agency.limits.workspaces'), $this->plans()->limit($team, 'workspaces'));
    }
}
