<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_platform_admin_is_not_mass_assignable(): void
    {
        $user = User::factory()->create();

        // Attempt to escalate via mass assignment.
        $user->fill(['is_platform_admin' => true]);
        $user->update(['name' => 'Updated', 'is_platform_admin' => true]);

        $this->assertFalse($user->fresh()->is_platform_admin);
    }

    public function test_suspended_organization_is_blocked_from_features_but_keeps_billing(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->currentTeam->update(['suspended_at' => now()]);

        // Feature routes are blocked...
        $this->actingAs($user)->get(route('workspaces.index'))->assertForbidden();

        // ...but billing stays reachable so they can reactivate.
        $this->actingAs($user)->get(route('billing.index'))->assertOk();
    }

    public function test_platform_admin_is_not_blocked_by_suspension(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $admin->currentTeam->update(['suspended_at' => now()]);

        $this->actingAs($admin)->get(route('workspaces.index'))->assertOk();
    }

    public function test_feed_url_pointing_at_internal_address_is_rejected(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $user->currentTeam->id, 'name' => 'W']);

        $this->actingAs($user)
            ->post(route('workspaces.feeds.store', $workspace), ['url' => 'http://169.254.169.254/latest/'])
            ->assertSessionHasErrors('url');

        $this->assertDatabaseCount('feeds', 0);
    }

    public function test_valid_public_feed_url_is_accepted(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $user->currentTeam->id, 'name' => 'W']);

        $this->actingAs($user)
            ->post(route('workspaces.feeds.store', $workspace), ['url' => 'https://blog.example.com/feed'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('feeds', 1);
    }

    public function test_ai_endpoint_requires_an_organization(): void
    {
        // A user with no team (no personal team) cannot burn AI credits.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('ai.generate'), ['topic' => 'x', 'providers' => ['linkedin']])
            ->assertForbidden();
    }
}
