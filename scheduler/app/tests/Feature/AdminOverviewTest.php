<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_cannot_access_the_overview(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)->get(route('admin.overview'))->assertForbidden();
    }

    public function test_platform_admin_sees_totals_and_recent_activity(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $suspendedOwner = User::factory()->withPersonalTeam()->create();
        $suspendedOwner->currentTeam->update(['suspended_at' => now()]);

        AuditLog::record('campaign.created', null, ['name' => 'Launch week'], teamId: $admin->currentTeam->id, userId: $admin->id);

        $this->actingAs($admin)
            ->get(route('admin.overview'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Overview')
                ->where('stats.users.total', 2)
                ->where('stats.organizations.total', 2)
                ->where('stats.organizations.suspended', 1)
                ->has('warnings', 1)
                ->has('recentSignups')
                ->has('recentAuditEvents', 1)
                ->where('recentAuditEvents.0.action', 'campaign.created'));
    }

    public function test_no_warnings_when_nothing_is_suspended_or_past_due(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();

        $this->actingAs($admin)
            ->get(route('admin.overview'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('warnings', 0));
    }

    public function test_organizations_per_plan_reflects_active_overrides(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $admin->currentTeam->update(['plan' => 'agency']);

        $overridden = User::factory()->withPersonalTeam()->create();
        $overridden->currentTeam->update(['plan' => 'free', 'plan_override' => 'scale']);

        $this->actingAs($admin)
            ->get(route('admin.overview'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.organizations.per_plan.scale', 1)
                ->where('stats.organizations.with_active_override', 1));
    }
}
