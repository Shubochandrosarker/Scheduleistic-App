<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_organization_owns_workspaces_with_channels(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $organization = $user->currentTeam;

        $workspace = Workspace::create([
            'team_id'     => $organization->id,
            'name'        => 'Acme Co',
            'client_name' => 'Acme',
            'timezone'    => 'America/New_York',
        ]);

        $channel = $workspace->channels()->create([
            'provider' => 'linkedin_company',
            'name'     => 'Acme LinkedIn Page',
            'status'   => 'connected',
        ]);

        $this->assertTrue($organization->workspaces->contains($workspace));
        $this->assertTrue($workspace->channels->contains($channel));
        $this->assertSame($organization->id, $workspace->team->id);
    }

    public function test_channel_oauth_tokens_are_encrypted_at_rest(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $workspace = Workspace::create([
            'team_id' => $user->currentTeam->id,
            'name'    => 'Acme Co',
        ]);

        $channel = $workspace->channels()->create([
            'provider'     => 'facebook',
            'name'         => 'Acme FB',
            'access_token' => 'super-secret-token',
        ]);

        // Accessor decrypts transparently...
        $this->assertSame('super-secret-token', $channel->fresh()->access_token);

        // ...but the raw stored value is ciphertext, not the plaintext.
        $raw = \DB::table('channels')->where('id', $channel->id)->value('access_token');
        $this->assertNotSame('super-secret-token', $raw);
        $this->assertSame('super-secret-token', Crypt::decryptString($raw));
    }

    public function test_a_user_can_be_assigned_to_a_workspace_with_a_role(): void
    {
        $owner  = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();

        $workspace = Workspace::create([
            'team_id' => $owner->currentTeam->id,
            'name'    => 'Acme Co',
        ]);

        $workspace->users()->attach($member, ['role' => 'client']);

        $this->assertSame('client', $workspace->fresh()->users->first()->pivot->role);
    }
}
