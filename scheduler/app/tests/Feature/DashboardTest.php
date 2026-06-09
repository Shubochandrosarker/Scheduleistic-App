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
}
