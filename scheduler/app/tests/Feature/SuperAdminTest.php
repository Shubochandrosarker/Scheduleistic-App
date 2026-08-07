<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ImpersonationService;
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
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();

        $this->actingAs($admin)
            ->get(route('admin.organizations.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Organizations')
                ->has('organizations')
                ->where('stats.organizations', 2));
    }

    public function test_admin_can_suspend_and_unsuspend_an_organization(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $target = User::factory()->withPersonalTeam()->create();
        $org = $target->currentTeam;

        $this->actingAs($admin)->post(route('admin.organizations.suspend', $org->id));
        $this->assertTrue($org->fresh()->isSuspended());

        $this->actingAs($admin)->post(route('admin.organizations.suspend', $org->id));
        $this->assertFalse($org->fresh()->isSuspended());
    }

    public function test_suspending_and_reactivating_an_organization_is_audited(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $target = User::factory()->withPersonalTeam()->create();
        $org = $target->currentTeam;

        $this->actingAs($admin)->post(route('admin.organizations.suspend', $org->id));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'organization.suspended',
            'user_id' => $admin->id,
            'team_id' => $org->id,
        ]);

        $this->actingAs($admin)->post(route('admin.organizations.suspend', $org->id));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'organization.reactivated',
            'user_id' => $admin->id,
            'team_id' => $org->id,
        ]);
    }

    public function test_impersonation_requires_a_confirmed_password(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $target = User::factory()->withPersonalTeam()->create();
        $org = $target->currentTeam;

        // No confirmed-password session: Fortify's password.confirm middleware
        // redirects to the confirmation screen instead of performing the action.
        $this->actingAs($admin)
            ->post(route('admin.organizations.impersonate', $org->id))
            ->assertRedirect(route('password.confirm'));

        $this->assertAuthenticatedAs($admin, 'web'); // still the admin, not impersonating
        $this->assertNull(session('impersonator_id'));
    }

    public function test_admin_can_impersonate_and_then_stop(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $target = User::factory()->withPersonalTeam()->create();
        $org = $target->currentTeam;

        // Impersonation logs in the target owner and records who started it.
        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.organizations.impersonate', $org->id))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('impersonator_id', $admin->id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'impersonation.started',
            'user_id' => $admin->id,
            'team_id' => $org->id,
        ]);

        // Stopping clears the impersonation marker, restores the admin, and
        // returns to the admin panel.
        $this->post(route('admin.stop-impersonating'))
            ->assertRedirect(route('admin.organizations.index'))
            ->assertSessionMissing('impersonator_id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'impersonation.stopped',
            'user_id' => $admin->id,
            'team_id' => $org->id,
        ]);

        // Note: asserted against the 'web' guard explicitly. Sanctum's
        // stateful guard caches its resolved user for the lifetime of the
        // guard instance — irrelevant across separate real requests (each
        // boots a fresh app), but this test process makes several requests
        // against the same instance, so the assertion targets the guard this
        // service actually drives.
        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_nested_impersonation_is_rejected(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $target = User::factory()->withPersonalTeam()->create();

        // A session that still carries an unclosed impersonation marker, even
        // though the current request re-authenticates as a platform admin —
        // defense in depth against a stale or tampered session, independent
        // of whatever real-world sequence could produce it.
        $this->actingAs($admin)
            ->withSession([
                'auth.password_confirmed_at' => time(),
                'impersonator_id' => $admin->id,
            ])
            ->post(route('admin.organizations.impersonate', $target->currentTeam->id))
            ->assertStatus(409);
    }

    public function test_impersonating_another_platform_admin_is_rejected(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $otherAdmin = User::factory()->platformAdmin()->withPersonalTeam()->create();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.organizations.impersonate', $otherAdmin->currentTeam->id))
            ->assertStatus(403);

        $this->assertNull(session('impersonator_id'));
    }

    public function test_a_demoted_admin_cannot_be_restored_on_stop(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $target = User::factory()->withPersonalTeam()->create();
        $org = $target->currentTeam;

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.organizations.impersonate', $org->id))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('impersonator_id', $admin->id);

        // The admin loses their privilege mid-session (e.g. revoked by another admin).
        $admin->forceFill(['is_platform_admin' => false])->save();

        $this->post(route('admin.stop-impersonating'))->assertStatus(403);

        // The impersonated user is not silently promoted back to an admin session.
        $this->assertAuthenticatedAs($target, 'web');
    }

    public function test_impersonation_expires_after_the_maximum_lifetime(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $target = User::factory()->withPersonalTeam()->create();
        $org = $target->currentTeam;

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.organizations.impersonate', $org->id));

        // Back-date the session past the maximum lifetime.
        $this->withSession([
            'impersonation_started_at' => now()->subMinutes(ImpersonationService::MAX_MINUTES + 1)->timestamp,
        ])->get(route('dashboard'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing('impersonator_id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'impersonation.expired',
            'user_id' => $admin->id,
        ]);

        $this->assertAuthenticatedAs($admin, 'web');
    }
}
