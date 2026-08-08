<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Jetstream\Features;
use Laravel\Jetstream\Mail\TeamInvitation;
use Tests\TestCase;

class InviteTeamMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_members_can_be_invited_to_team(): void
    {
        if (! Features::sendsTeamInvitations()) {
            $this->markTestSkipped('Team invitations not enabled.');
        }

        Mail::fake();

        $this->actingAs($user = User::factory()->withPersonalTeam()->create());
        // The free plan's member limit is 1 — the owner alone already fills
        // it, so a plan with room is needed to test a *successful* invite.
        $user->currentTeam->update(['plan' => 'pro']);

        $this->post('/teams/'.$user->currentTeam->id.'/members', [
            'email' => 'test@example.com',
            'role' => 'admin',
        ]);

        Mail::assertSent(TeamInvitation::class);

        $this->assertCount(1, $user->currentTeam->fresh()->teamInvitations);
    }

    public function test_an_invitation_is_rejected_once_the_plan_seat_limit_is_reached(): void
    {
        if (! Features::sendsTeamInvitations()) {
            $this->markTestSkipped('Team invitations not enabled.');
        }

        Mail::fake();

        $this->actingAs($user = User::factory()->withPersonalTeam()->create());
        $team = $user->currentTeam;
        $team->update(['plan' => 'pro']); // limit: 5 members

        // The owner plus 4 pending invitations already fill every seat —
        // enforcement must count a pending invitation as occupying one,
        // not just an accepted membership, or an organization could send
        // unlimited invites and blow past its limit the moment they're all
        // accepted together.
        foreach (range(1, 4) as $i) {
            $team->teamInvitations()->create(['email' => "member{$i}@example.com", 'role' => 'admin']);
        }

        $response = $this->post('/teams/'.$team->id.'/members', [
            'email' => 'one-too-many@example.com',
            'role' => 'admin',
        ]);

        Mail::assertNotSent(TeamInvitation::class);
        $this->assertCount(4, $team->fresh()->teamInvitations);

        $response->assertSessionHasErrors('email', null, 'addTeamMember');
        $errors = $response->getSession()->get('errors')->getBag('addTeamMember');
        $this->assertStringContainsString('member limit', $errors->first('email'));
    }

    public function test_team_member_invitations_can_be_cancelled(): void
    {
        if (! Features::sendsTeamInvitations()) {
            $this->markTestSkipped('Team invitations not enabled.');
        }

        Mail::fake();

        $this->actingAs($user = User::factory()->withPersonalTeam()->create());

        $invitation = $user->currentTeam->teamInvitations()->create([
            'email' => 'test@example.com',
            'role' => 'admin',
        ]);

        $this->delete('/team-invitations/'.$invitation->id);

        $this->assertCount(0, $user->currentTeam->fresh()->teamInvitations);
    }
}
