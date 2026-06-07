<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_cannot_access_the_control_plane(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->get(route('admin.organizations.index'))
            ->assertForbidden();
    }

    public function test_platform_admin_sees_all_organizations(): void
    {
        User::factory()->withPersonalTeam()->create(); // some other org
        $admin = User::factory()->withPersonalTeam()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.organizations.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Organizations')
                ->has('organizations')
                ->where('stats.organizations', 2));
    }

    public function test_admin_can_suspend_and_unsuspend_an_organization(): void
    {
        $admin = User::factory()->withPersonalTeam()->create(['is_platform_admin' => true]);
        $target = User::factory()->withPersonalTeam()->create();
        $org = $target->currentTeam;

        $this->actingAs($admin)->post(route('admin.organizations.suspend', $org->id));
        $this->assertTrue($org->fresh()->isSuspended());

        $this->actingAs($admin)->post(route('admin.organizations.suspend', $org->id));
        $this->assertFalse($org->fresh()->isSuspended());
    }

    public function test_admin_can_impersonate_and_then_stop(): void
    {
        $admin = User::factory()->withPersonalTeam()->create(['is_platform_admin' => true]);
        $target = User::factory()->withPersonalTeam()->create();
        $org = $target->currentTeam;

        // Impersonation logs in the target owner and records who started it.
        $this->actingAs($admin)
            ->post(route('admin.organizations.impersonate', $org->id))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('impersonator_id', $admin->id);

        // Stopping clears the impersonation marker and returns to the admin panel.
        $this->post(route('admin.stop-impersonating'))
            ->assertRedirect(route('admin.organizations.index'))
            ->assertSessionMissing('impersonator_id');
    }
}
