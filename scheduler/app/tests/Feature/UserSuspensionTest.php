<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * User-level suspension (a single account) is distinct from organization
 * suspension (tested in TenantIsolationTest/SuperAdminTest) — see
 * App\Http\Middleware\EnsureUserActive.
 */
class UserSuspensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_active_user_is_not_suspended(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->assertFalse($user->isSuspended());
    }

    public function test_a_suspended_user_cannot_reach_the_authenticated_app(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $user = User::factory()->withPersonalTeam()->create();

        $user->forceFill([
            'suspended_at' => now(),
            'suspension_reason' => 'Abuse report',
            'suspended_by' => $admin->id,
        ])->save();

        $this->assertTrue($user->fresh()->isSuspended());
        $this->assertSame($admin->id, $user->fresh()->suspendedBy->id);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_reactivating_a_user_restores_access(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->forceFill(['suspended_at' => now()])->save();

        $user->forceFill(['suspended_at' => null, 'suspension_reason' => null, 'suspended_by' => null])->save();

        $this->assertFalse($user->fresh()->isSuspended());

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_a_suspended_user_can_still_stop_impersonating(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $target = User::factory()->withPersonalTeam()->create();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.organizations.impersonate', $target->currentTeam->id));

        // Suspended mid-session (e.g. by another admin, or as part of the
        // same support action) — must not trap the browser as a locked-out
        // impersonated account.
        $target->forceFill(['suspended_at' => now()])->save();

        $this->post(route('admin.stop-impersonating'))
            ->assertRedirect(route('admin.organizations.index'));

        $this->assertAuthenticatedAs($admin, 'web');
    }
}
