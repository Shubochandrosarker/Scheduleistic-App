<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Two ends of the same fix: a driver that resolves a page/location/board/org
 * at connect time can find none to offer (previously a raw 500 or a
 * silently half-created channel), and a channel that was offered several
 * can be switched between them without a fresh OAuth round-trip.
 */
class ChannelAccountSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function actingUserWithWorkspace(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $user->currentTeam->id, 'name' => 'Acme']);

        return [$user, $workspace];
    }

    public function test_a_provider_that_finds_no_accounts_shows_a_clear_error_instead_of_a_500(): void
    {
        config(['services.google_business.client_id' => 'id', 'services.google_business.client_secret' => 'secret']);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'user-token']),
            'mybusiness.googleapis.com/v4/accounts' => Http::response(['accounts' => []]),
        ]);

        [$user, $workspace] = $this->actingUserWithWorkspace();

        $response = $this->actingAs($user)
            ->withSession([
                'oauth.state' => 'state-1',
                'oauth.workspace' => $workspace->id,
                'oauth.provider' => 'google_business',
            ])
            ->get(route('channels.callback', 'google_business').'?state=state-1&code=auth-code');

        $response->assertRedirect(route('workspaces.channels.index', $workspace));
        $response->assertSessionHasErrors('provider');
        $this->assertDatabaseCount('channels', 0);
    }

    public function test_selecting_a_different_account_updates_the_channel(): void
    {
        [$user, $workspace] = $this->actingUserWithWorkspace();

        $channel = $workspace->channels()->create([
            'provider' => 'pinterest',
            'name' => 'Recipes',
            'access_token' => 'tok',
            'meta' => [
                'board_id' => 'board-1',
                'available_accounts' => [
                    ['id' => 'board-1', 'name' => 'Recipes'],
                    ['id' => 'board-2', 'name' => 'Travel'],
                ],
            ],
        ]);

        $this->actingAs($user)
            ->put(route('workspaces.channels.select-account', [$workspace->id, $channel->id]), ['account_id' => 'board-2'])
            ->assertRedirect();

        $channel->refresh();
        $this->assertSame('board-2', $channel->meta['board_id']);
        $this->assertSame('Travel', $channel->name);
    }

    public function test_a_user_cannot_switch_a_channel_belonging_to_another_organizations_workspace(): void
    {
        [$user] = $this->actingUserWithWorkspace();
        $other = User::factory()->withPersonalTeam()->create();
        $foreignWorkspace = Workspace::create(['team_id' => $other->currentTeam->id, 'name' => 'Foreign']);

        $channel = $foreignWorkspace->channels()->create([
            'provider' => 'pinterest',
            'name' => 'Their board',
            'access_token' => 'tok',
            'meta' => ['board_id' => 'board-1', 'available_accounts' => [['id' => 'board-1', 'name' => 'Their board']]],
        ]);

        $this->actingAs($user)
            ->put(route('workspaces.channels.select-account', [$foreignWorkspace->id, $channel->id]), ['account_id' => 'board-1'])
            ->assertForbidden();
    }

    public function test_selecting_an_account_for_a_provider_with_no_picker_is_a_harmless_no_op(): void
    {
        [$user, $workspace] = $this->actingUserWithWorkspace();

        $channel = $workspace->channels()->create([
            'provider' => 'mastodon',
            'name' => 'Masto',
            'access_token' => 'tok',
            'meta' => ['instance' => 'https://mastodon.social'],
        ]);

        $this->actingAs($user)
            ->put(route('workspaces.channels.select-account', [$workspace->id, $channel->id]), ['account_id' => 'anything'])
            ->assertRedirect();

        $this->assertSame('Masto', $channel->fresh()->name);
    }
}
