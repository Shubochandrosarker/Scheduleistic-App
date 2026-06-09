<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelConnectGapsTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $user->currentTeam->id, 'name' => 'W']);

        return [$user, $workspace];
    }

    public function test_token_provider_can_be_connected_with_credentials(): void
    {
        [$user, $workspace] = $this->workspace();

        $this->actingAs($user)
            ->post(route('workspaces.channels.token', [$workspace->id, 'mastodon']), [
                'instance'     => 'https://mastodon.social',
                'access_token' => 'secret-token',
            ])
            ->assertRedirect(route('workspaces.channels.index', $workspace));

        $this->assertDatabaseHas('channels', [
            'workspace_id' => $workspace->id,
            'provider'     => 'mastodon',
            'status'       => 'connected',
        ]);

        $channel = $workspace->channels()->first();
        $this->assertSame('https://mastodon.social', $channel->meta['instance']);
        $this->assertSame('secret-token', $channel->access_token); // decrypts via cast
    }

    public function test_token_connect_validates_required_fields(): void
    {
        [$user, $workspace] = $this->workspace();

        $this->actingAs($user)
            ->post(route('workspaces.channels.token', [$workspace->id, 'wordpress']), [
                'site_url' => 'https://blog.example.com',
                // missing username + app_password
            ])
            ->assertSessionHasErrors(['username', 'app_password']);
    }

    public function test_oauth_connect_to_unconfigured_provider_is_rejected(): void
    {
        [$user, $workspace] = $this->workspace();

        // No PINTEREST_CLIENT_ID is set in the test env.
        $this->actingAs($user)
            ->get(route('workspaces.channels.connect', [$workspace->id, 'pinterest']))
            ->assertStatus(503);
    }

    public function test_oauth_connect_to_token_provider_redirects_back(): void
    {
        [$user, $workspace] = $this->workspace();

        $this->actingAs($user)
            ->get(route('workspaces.channels.connect', [$workspace->id, 'bluesky']))
            ->assertSessionHasErrors('provider');
    }

    public function test_channels_index_marks_provider_type_and_config(): void
    {
        [$user, $workspace] = $this->workspace();

        $this->actingAs($user)
            ->get(route('workspaces.channels.index', $workspace))
            ->assertInertia(fn ($page) => $page
                ->component('Channels/Index')
                ->has('providers'));
    }
}
