<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 6: while a platform admin is impersonating a user, a fixed set of
 * identity-changing, financial, and irreversible actions is blocked outright
 * — unless an explicit, audited break-glass reason is supplied. Ordinary
 * content management stays available throughout, since that is the entire
 * point of impersonating someone to fix their account.
 *
 * Two ways of getting into an "impersonating" state are used here, chosen
 * per test:
 *
 *  - `impersonate()` drives the real start-impersonation route. This is
 *    enough for every "is it blocked" assertion, since the blocking
 *    middleware only ever reads `session()->has('impersonator_id')` — but
 *    it is NOT enough when the *downstream controller* also needs to see
 *    the impersonated user as `$request->user()`. Sanctum's stateful guard
 *    caches its resolved user for the guard instance's lifetime; a second
 *    `$this->call()` in the same test method reuses that instance (real
 *    traffic never does — every request boots fresh), so it can keep
 *    resolving whoever the *first* call authenticated as.
 *  - `impersonationSession()` builds the session markers directly and pairs
 *    them with `actingAs($target)`, so the single request both carries the
 *    impersonation markers my middleware reads AND correctly authenticates
 *    as the impersonated user for anything downstream that reads
 *    `$request->user()` — used wherever a test needs the latter.
 */
class ImpersonationHighRiskActionTest extends TestCase
{
    use RefreshDatabase;

    protected function impersonate(User $admin, Team $organization): void
    {
        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.organizations.impersonate', $organization->id))
            ->assertRedirect(route('dashboard'));
    }

    /** @return array<string, mixed> */
    protected function impersonationSession(User $admin, User $target): array
    {
        return [
            'impersonator_id' => $admin->id,
            'impersonated_user_id' => $target->id,
            'impersonating_team_id' => $target->currentTeam->id,
            'impersonation_started_at' => now()->timestamp,
        ];
    }

    public function test_billing_checkout_is_blocked_while_impersonating(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $target = User::factory()->withPersonalTeam()->create();
        $this->impersonate($admin, $target->currentTeam);

        $this->post(route('billing.checkout', 'growth'))->assertForbidden();
    }

    public function test_billing_portal_is_blocked_while_impersonating(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $target = User::factory()->withPersonalTeam()->create();
        $this->impersonate($admin, $target->currentTeam);

        $this->get(route('billing.portal'))->assertForbidden();
    }

    public function test_password_change_is_blocked_while_impersonating(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $target = User::factory()->withPersonalTeam()->create();
        $this->impersonate($admin, $target->currentTeam);

        $this->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'a-new-password-123',
            'password_confirmation' => 'a-new-password-123',
        ])->assertForbidden();

        $this->assertTrue(Hash::check('password', $target->fresh()->password));
    }

    public function test_two_factor_authentication_cannot_be_toggled_while_impersonating(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $target = User::factory()->withPersonalTeam()->create();
        $this->impersonate($admin, $target->currentTeam);

        $this->post(route('two-factor.enable'))->assertForbidden();
        $this->delete(route('two-factor.disable'))->assertForbidden();
    }

    public function test_account_deletion_is_blocked_while_impersonating(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $target = User::factory()->withPersonalTeam()->create();
        $this->impersonate($admin, $target->currentTeam);

        $this->delete(route('current-user.destroy'))->assertForbidden();

        $this->assertNotNull($target->fresh());
    }

    public function test_organization_deletion_is_blocked_while_impersonating(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $target = User::factory()->withPersonalTeam()->create();
        $this->impersonate($admin, $target->currentTeam);

        $this->delete(route('teams.destroy', $target->currentTeam->id))->assertForbidden();

        $this->assertDatabaseHas('teams', ['id' => $target->currentTeam->id]);
    }

    public function test_workspace_deletion_is_blocked_while_impersonating(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $target = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $target->currentTeam->id, 'name' => 'Brand']);
        $this->impersonate($admin, $target->currentTeam);

        $this->delete(route('workspaces.destroy', $workspace->id))->assertForbidden();

        $this->assertNotNull($workspace->fresh());
    }

    public function test_ordinary_content_actions_stay_available_while_impersonating(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $target = User::factory()->withPersonalTeam()->create();
        $target->currentTeam->update(['plan' => 'scale']); // unlocks the campaigns capability
        $workspace = Workspace::create(['team_id' => $target->currentTeam->id, 'name' => 'Brand']);
        $campaign = Campaign::create(['workspace_id' => $workspace->id, 'name' => 'Launch']);

        $this->actingAs($target)
            ->withSession($this->impersonationSession($admin, $target))
            ->delete(route('campaigns.destroy', $campaign->id))
            ->assertRedirect();

        $this->assertNull(Campaign::find($campaign->id));
    }

    public function test_the_same_actions_are_unaffected_outside_impersonation(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $owner->currentTeam->id, 'name' => 'Brand']);

        $this->actingAs($owner)
            ->delete(route('workspaces.destroy', $workspace->id))
            ->assertRedirect();

        $this->assertNull(Workspace::find($workspace->id));
    }

    public function test_a_break_glass_reason_allows_the_action_through_and_is_audited(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $target = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $target->currentTeam->id, 'name' => 'Brand']);

        // Sent as a query parameter, not a DELETE body field: Laravel's test
        // client does not parse a form-encoded DELETE body into `input()`,
        // and the middleware itself must accept the reason regardless of
        // HTTP method — a query parameter is the one channel every verb has.
        $url = route('workspaces.destroy', [
            'workspace' => $workspace->id,
            'break_glass_reason' => 'Tenant asked us on a support call to delete this test brand.',
        ]);

        $this->actingAs($target)
            ->withSession($this->impersonationSession($admin, $target))
            ->delete($url)
            ->assertRedirect();

        $this->assertNull(Workspace::find($workspace->id));

        $log = AuditLog::where('action', 'impersonation.break_glass')->firstOrFail();

        // Attributed to the real admin acting under the tenant's identity,
        // never to the impersonated user themselves.
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($target->currentTeam->id, $log->team_id);
        $this->assertSame('workspaces.destroy', $log->context['route']);
        $this->assertSame($target->id, $log->context['owner_id']);
        $this->assertStringContainsString('support call', $log->context['reason']);
    }

    public function test_a_blank_break_glass_reason_does_not_bypass_the_block(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $target = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $target->currentTeam->id, 'name' => 'Brand']);

        $url = route('workspaces.destroy', ['workspace' => $workspace->id, 'break_glass_reason' => '   ']);

        $this->impersonate($admin, $target->currentTeam);

        $this->delete($url)->assertForbidden();

        $this->assertNotNull($workspace->fresh());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'impersonation.break_glass']);
    }
}
