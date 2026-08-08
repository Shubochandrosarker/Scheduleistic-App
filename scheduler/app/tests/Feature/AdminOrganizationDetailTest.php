<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminOrganizationDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function confirmed(User $admin): TestCase
    {
        return $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_non_admins_cannot_view_the_detail_page(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $other = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)->get(route('admin.organizations.show', $other->currentTeam->id))->assertForbidden();
    }

    public function test_the_detail_page_shows_profile_owner_members_and_workspaces(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();
        $owner->currentTeam->users()->attach($member, ['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.organizations.show', $owner->currentTeam->id))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Organizations/Show')
                ->where('organization.id', $owner->currentTeam->id)
                ->where('owner.id', $owner->id)
                ->has('members', 1)
                ->where('members.0.id', $member->id)
                ->where('members.0.role', 'admin'));
    }

    public function test_plan_override_requires_a_confirmed_password(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $owner = User::factory()->withPersonalTeam()->create();

        $this->actingAs($admin)
            ->post(route('admin.organizations.plan-override.update', $owner->currentTeam->id), [
                'plan' => 'scale', 'reason' => 'VIP customer',
            ])
            ->assertRedirect(route('password.confirm'));

        $this->assertNull($owner->currentTeam->fresh()->plan_override);
    }

    public function test_admin_can_grant_a_plan_override_with_audit(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;

        $this->confirmed($admin)
            ->post(route('admin.organizations.plan-override.update', $team->id), [
                'plan' => 'scale',
                'reason' => 'VIP customer, comped for a year',
            ])
            ->assertRedirect();

        $team->refresh();
        $this->assertSame('scale', $team->plan_override);
        $this->assertSame($admin->id, $team->plan_override_set_by);
        $this->assertSame('scale', app(PlanService::class)->planKey($team));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'organization.plan_override_granted',
            'user_id' => $admin->id,
            'team_id' => $team->id,
        ]);
    }

    public function test_an_unknown_plan_key_is_rejected(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $owner = User::factory()->withPersonalTeam()->create();

        $this->confirmed($admin)
            ->post(route('admin.organizations.plan-override.update', $owner->currentTeam->id), [
                'plan' => 'not-a-real-plan',
                'reason' => 'test',
            ])
            ->assertSessionHasErrors('plan');
    }

    public function test_granting_an_override_without_a_reason_is_rejected(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $owner = User::factory()->withPersonalTeam()->create();

        $this->confirmed($admin)
            ->post(route('admin.organizations.plan-override.update', $owner->currentTeam->id), ['plan' => 'scale'])
            ->assertSessionHasErrors('reason');
    }

    public function test_removing_an_override_restores_the_base_plan_and_requires_a_reason(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;
        $team->update(['plan' => 'free', 'plan_override' => 'scale', 'plan_override_set_by' => $admin->id]);

        // No reason: rejected, override still active.
        $this->confirmed($admin)
            ->delete(route('admin.organizations.plan-override.destroy', $team->id), [])
            ->assertSessionHasErrors('reason');
        $this->assertNotNull($team->fresh()->plan_override);

        $this->confirmed($admin)
            ->delete(route('admin.organizations.plan-override.destroy', $team->id), ['reason' => 'Trial period ended'])
            ->assertRedirect();

        $team->refresh();
        $this->assertNull($team->plan_override);
        $this->assertSame('free', app(PlanService::class)->planKey($team));

        $this->assertDatabaseHas('audit_logs', ['action' => 'organization.plan_override_removed', 'team_id' => $team->id]);
    }

    public function test_resync_plan_reconciles_from_the_local_subscription_and_is_audited(): void
    {
        config(['plans.pro.price_id' => 'price_pro_resync']);

        $admin = User::factory()->platformAdmin()->create();
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;
        $team->forceFill(['stripe_id' => 'cus_resync'])->save();
        $team->subscriptions()->create([
            'type' => 'default', 'stripe_id' => 'sub_resync', 'stripe_status' => 'active',
            'stripe_price' => 'price_pro_resync', 'quantity' => 1,
        ]);

        $this->assertSame('free', $team->plan);

        $this->actingAs($admin)
            ->post(route('admin.organizations.resync-plan', $team->id))
            ->assertRedirect();

        $this->assertSame('pro', $team->fresh()->plan);
        $this->assertDatabaseHas('audit_logs', ['action' => 'organization.plan_resynced', 'team_id' => $team->id]);
    }

    public function test_admin_can_grant_and_clear_a_capability_entitlement(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam; // free plan: no capabilities granted

        $this->assertFalse(app(PlanService::class)->can($team, 'white_label'));

        $this->actingAs($admin)
            ->post(route('admin.organizations.entitlements.grant', $team->id), [
                'type' => 'capability', 'key' => 'white_label', 'reason' => 'Grandfathered from legacy plan',
            ])
            ->assertRedirect();

        $team->refresh();
        $this->assertTrue(app(PlanService::class)->can($team, 'white_label'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'organization.entitlement_granted', 'team_id' => $team->id]);

        $this->actingAs($admin)
            ->delete(route('admin.organizations.entitlements.clear', $team->id), [
                'type' => 'capability', 'key' => 'white_label', 'reason' => 'Grandfathering period ended',
            ])
            ->assertRedirect();

        $this->assertFalse(app(PlanService::class)->can($team->fresh(), 'white_label'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'organization.entitlement_cleared', 'team_id' => $team->id]);
    }

    public function test_admin_can_raise_and_clear_a_limit_entitlement(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;

        $plans = app(PlanService::class);
        $freeLimit = $plans->limit($team, 'workspaces');

        $this->actingAs($admin)
            ->post(route('admin.organizations.entitlements.grant', $team->id), [
                'type' => 'limit', 'key' => 'workspaces', 'value' => 99, 'reason' => 'Special deal',
            ])
            ->assertRedirect();

        $this->assertSame(99, $plans->limit($team->fresh(), 'workspaces'));

        $this->actingAs($admin)
            ->delete(route('admin.organizations.entitlements.clear', $team->id), [
                'type' => 'limit', 'key' => 'workspaces', 'reason' => 'Deal ended',
            ])
            ->assertRedirect();

        $this->assertSame($freeLimit, $plans->limit($team->fresh(), 'workspaces'));
    }

    public function test_a_limit_override_can_never_lower_the_plan_limit(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;
        $team->update(['plan' => 'pro']);

        $plans = app(PlanService::class);
        $proLimit = $plans->limit($team, 'workspaces');

        $this->actingAs($admin)
            ->post(route('admin.organizations.entitlements.grant', $team->id), [
                'type' => 'limit', 'key' => 'workspaces', 'value' => 1, 'reason' => 'Attempted lower override',
            ])
            ->assertRedirect();

        // The stored override is 1, but PlanService::limits() takes the max —
        // the pro plan's own limit still wins.
        $this->assertSame($proLimit, $plans->limit($team->fresh(), 'workspaces'));
    }

    public function test_an_unknown_capability_key_is_rejected(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $owner = User::factory()->withPersonalTeam()->create();

        $this->actingAs($admin)
            ->post(route('admin.organizations.entitlements.grant', $owner->currentTeam->id), [
                'type' => 'capability', 'key' => 'not_a_real_capability', 'reason' => 'test',
            ])
            ->assertSessionHasErrors('key');
    }

    public function test_audit_history_tab_shows_this_organizations_events_only(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $owner = User::factory()->withPersonalTeam()->create();
        $otherOwner = User::factory()->withPersonalTeam()->create();

        AuditLog::record('organization.suspended', $owner->currentTeam, [], teamId: $owner->currentTeam->id, userId: $admin->id);
        AuditLog::record('organization.suspended', $otherOwner->currentTeam, [], teamId: $otherOwner->currentTeam->id, userId: $admin->id);

        $this->actingAs($admin)
            ->get(route('admin.organizations.show', $owner->currentTeam->id))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('auditHistory.data', 1)
                ->where('auditHistory.data.0.action', 'organization.suspended'));
    }
}
