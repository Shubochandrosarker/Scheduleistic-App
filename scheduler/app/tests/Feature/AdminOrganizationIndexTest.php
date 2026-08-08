<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Replaces the previous unbounded latest()->limit(500)->get() client-side
 * filtered list with real server-side pagination/search/filters — see
 * App\Services\Admin\AdminOrganizationQuery.
 */
class AdminOrganizationIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_index_is_paginated_not_capped_at_500(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        User::factory()->count(12)->withPersonalTeam()->create();

        $this->actingAs($admin)
            ->get(route('admin.organizations.index', ['per_page' => 10]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Organizations')
                ->has('organizations.data', 10)
                ->where('organizations.total', 13)); // 12 + the admin's own
    }

    public function test_search_matches_organization_name_owner_or_domain(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();

        $owner = User::factory()->withPersonalTeam()->create();
        $owner->currentTeam->update(['name' => 'Acme Agency']);

        $other = User::factory()->withPersonalTeam()->create();
        $other->currentTeam->update(['name' => 'Unrelated Co']);

        $this->actingAs($admin)
            ->get(route('admin.organizations.index', ['search' => 'acme']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('organizations.data', 1)
                ->where('organizations.data.0.id', $owner->currentTeam->id));
    }

    public function test_status_filter_narrows_to_suspended_organizations(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();

        $suspendedOwner = User::factory()->withPersonalTeam()->create();
        $suspendedOwner->currentTeam->update(['suspended_at' => now()]);

        User::factory()->withPersonalTeam()->create(); // active

        $this->actingAs($admin)
            ->get(route('admin.organizations.index', ['status' => 'suspended']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('organizations.data', 1)
                ->where('organizations.data.0.id', $suspendedOwner->currentTeam->id));
    }

    public function test_effective_plan_filter_finds_an_org_by_its_active_override(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        // Keep the admin's own default-plan org out of the free/scale counts below.
        $admin->currentTeam->update(['plan' => 'agency']);

        $overridden = User::factory()->withPersonalTeam()->create();
        $overridden->currentTeam->update(['plan' => 'free', 'plan_override' => 'scale']);

        $plainScale = User::factory()->withPersonalTeam()->create();
        $plainScale->currentTeam->update(['plan' => 'scale']);

        User::factory()->withPersonalTeam()->create()->currentTeam->update(['plan' => 'free']);

        $this->actingAs($admin)
            ->get(route('admin.organizations.index', ['plan' => 'scale']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('organizations.data', 2)); // the override AND the plain scale org, not the free one

        // A free-base-plan org with an active "scale" override must NOT be
        // findable under a filter for "free" — the override, not the base
        // plan, is what's effective.
        $this->actingAs($admin)
            ->get(route('admin.organizations.index', ['plan' => 'free']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('organizations.data', 1)); // just the untouched free org
    }

    public function test_base_plan_filter_ignores_the_override(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        // Keep the admin's own default-plan ('free') org from also matching below.
        $admin->currentTeam->update(['plan' => 'agency']);

        $overridden = User::factory()->withPersonalTeam()->create();
        $overridden->currentTeam->update(['plan' => 'free', 'plan_override' => 'scale']);

        $this->actingAs($admin)
            ->get(route('admin.organizations.index', ['base_plan' => 'free']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('organizations.data', 1)
                ->where('organizations.data.0.id', $overridden->currentTeam->id)
                ->where('organizations.data.0.effective_plan', 'scale')
                ->where('organizations.data.0.base_plan', 'free'));
    }

    public function test_an_expired_override_does_not_match_the_effective_plan_filter(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        // Keep the admin's own default-plan ('free') org from also matching below.
        $admin->currentTeam->update(['plan' => 'agency']);

        $expired = User::factory()->withPersonalTeam()->create();
        $expired->currentTeam->update([
            'plan' => 'free',
            'plan_override' => 'scale',
            'plan_override_expires_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.organizations.index', ['plan' => 'scale']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('organizations.data', 0));

        $this->actingAs($admin)
            ->get(route('admin.organizations.index', ['plan' => 'free']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('organizations.data', 1)
                ->where('organizations.data.0.id', $expired->currentTeam->id));
    }

    public function test_invalid_plan_filter_is_rejected(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.organizations.index', ['plan' => 'not-a-real-plan']))
            ->assertSessionHasErrors('plan');
    }
}
