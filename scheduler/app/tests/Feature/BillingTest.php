<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_index_sends_each_plans_own_features_not_an_empty_field(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get(route('billing.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Billing/Index')
                ->where('plans.0.features.ai_captions', false) // free
                ->where('plans.1.features.ai_captions', true)); // solo
    }

    public function test_checkout_rejects_a_duplicate_subscription(): void
    {
        config(['plans.pro.price_id' => 'price_pro_123']);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $team->forceFill(['stripe_id' => 'cus_123'])->save();
        $team->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_123',
            'stripe_status' => 'active',
            'stripe_price' => 'price_pro_123',
            'quantity' => 1,
        ]);

        // The guard must trip before Cashier ever attempts a real Stripe call.
        $this->actingAs($user)
            ->post(route('billing.checkout', 'pro'))
            ->assertRedirect()
            ->assertSessionHasErrors('plan');

        $this->assertSame(1, $team->subscriptions()->count());
    }

    public function test_checkout_requires_the_organization_owner(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;

        $member = User::factory()->create();
        $team->users()->attach($member, ['role' => 'admin']);
        $member->switchTeam($team);

        config(['plans.pro.price_id' => 'price_pro_123']);

        $this->actingAs($member)
            ->post(route('billing.checkout', 'pro'))
            ->assertForbidden();
    }
}
