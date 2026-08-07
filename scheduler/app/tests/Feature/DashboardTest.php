<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_real_stats_not_the_jetstream_scaffold(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Workspace::create(['team_id' => $user->currentTeam->id, 'name' => 'Acme']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->where('stats.workspaces', 1)
                ->has('usage')
                ->has('upcoming')
                ->where('plan', 'free'));
    }

    public function test_public_landing_page_renders(): void
    {
        $this->get('/')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Welcome'));
    }

    public function test_the_dashboard_and_shared_sidebar_prop_both_show_the_effective_plan(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->currentTeam->update([
            // Base plan stays 'free'; an active override should be what both
            // the dashboard's own prop and the globally-shared sidebar prop
            // report — a platform-admin override must be visible everywhere,
            // not just on the billing page.
            'plan_override' => 'scale',
            'plan_override_expires_at' => now()->addWeek(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('plan', 'scale')
                ->where('hasActiveOverride', true)
                ->where('planName', 'Scale'));
    }
}
