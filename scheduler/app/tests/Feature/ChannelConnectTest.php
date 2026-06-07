<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\User;
use App\Models\Workspace;
use App\Social\ProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelConnectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(ProviderManager::class, new ProviderManager(fake: true));
    }

    private function actingUserWithWorkspace(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $user->currentTeam->id, 'name' => 'Acme']);

        return [$user, $workspace];
    }

    public function test_connect_redirects_to_provider_and_stores_state(): void
    {
        [$user, $workspace] = $this->actingUserWithWorkspace();

        $response = $this->actingAs($user)
            ->get(route('workspaces.channels.connect', [$workspace->id, 'linkedin']));

        $response->assertRedirect();
        $response->assertSessionHas('oauth.state');
        $response->assertSessionHas('oauth.workspace', $workspace->id);
    }

    public function test_callback_creates_a_channel(): void
    {
        [$user, $workspace] = $this->actingUserWithWorkspace();

        $response = $this->actingAs($user)
            ->withSession([
                'oauth.state'     => 'state-123',
                'oauth.workspace' => $workspace->id,
                'oauth.provider'  => 'linkedin',
            ])
            ->get(route('channels.callback', 'linkedin').'?state=state-123&code=auth-code');

        $response->assertRedirect(route('workspaces.channels.index', $workspace));

        $this->assertDatabaseHas('channels', [
            'workspace_id' => $workspace->id,
            'provider'     => 'linkedin',
            'status'       => 'connected',
        ]);
    }

    public function test_callback_rejects_mismatched_state(): void
    {
        [$user, $workspace] = $this->actingUserWithWorkspace();

        $this->actingAs($user)
            ->withSession(['oauth.state' => 'real-state', 'oauth.workspace' => $workspace->id])
            ->get(route('channels.callback', 'linkedin').'?state=forged&code=x')
            ->assertForbidden();

        $this->assertDatabaseCount('channels', 0);
    }

    public function test_a_user_cannot_connect_channels_for_another_organizations_workspace(): void
    {
        [$user] = $this->actingUserWithWorkspace();
        $other  = User::factory()->withPersonalTeam()->create();
        $foreign = Workspace::create(['team_id' => $other->currentTeam->id, 'name' => 'Foreign']);

        $this->actingAs($user)
            ->get(route('workspaces.channels.connect', [$foreign->id, 'linkedin']))
            ->assertForbidden();
    }
}
