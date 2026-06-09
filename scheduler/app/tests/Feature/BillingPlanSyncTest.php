<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingPlanSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_is_derived_from_an_active_subscription(): void
    {
        config(['plans.pro.price_id' => 'price_pro_123']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $this->assertSame('free', $team->plan);

        $team->forceFill(['stripe_id' => 'cus_123'])->save();
        $team->subscriptions()->create([
            'type'          => 'default',
            'stripe_id'     => 'sub_123',
            'stripe_status' => 'active',
            'stripe_price'  => 'price_pro_123',
            'quantity'      => 1,
        ]);

        $team->syncPlanFromSubscription();

        $this->assertSame('pro', $team->fresh()->plan);
    }

    public function test_plan_falls_back_to_free_without_an_active_subscription(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $team->forceFill(['plan' => 'pro'])->save();

        // No subscription -> reconciles back to free.
        $team->syncPlanFromSubscription();

        $this->assertSame('free', $team->fresh()->plan);
    }

    public function test_canceled_subscription_drops_to_free(): void
    {
        config(['plans.agency.price_id' => 'price_agency']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $team->forceFill(['stripe_id' => 'cus_x', 'plan' => 'agency'])->save();
        $team->subscriptions()->create([
            'type'          => 'default',
            'stripe_id'     => 'sub_x',
            'stripe_status' => 'canceled',
            'stripe_price'  => 'price_agency',
            'quantity'      => 1,
            'ends_at'       => now()->subDay(),
        ]);

        $team->syncPlanFromSubscription();

        $this->assertSame('free', $team->fresh()->plan);
    }
}
