<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PlanService;
use App\Services\UsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_plan_allows_the_first_workspace_but_blocks_the_second(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->assertSame('free', $user->currentTeam->plan);

        $this->actingAs($user)
            ->post(route('workspaces.store'), ['name' => 'First'])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('workspaces.store'), ['name' => 'Second'])
            ->assertSessionHasErrors('plan');

        $this->assertDatabaseCount('workspaces', 1);
    }

    public function test_usage_service_reports_usage_and_remaining(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        Workspace::create(['team_id' => $team->id, 'name' => 'W1']);

        $usage = app(UsageService::class);

        $this->assertSame(1, $usage->usage($team, 'workspaces'));
        $this->assertSame(0, $usage->remaining($team, 'workspaces')); // free limit is 1
        $this->assertFalse($usage->allows($team, 'workspaces'));
    }

    public function test_channel_limit_blocks_connecting_more_accounts(): void
    {
        config(['plans.free.limits.channels' => 1]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $workspace = Workspace::create(['team_id' => $team->id, 'name' => 'W']);
        $workspace->channels()->create(['provider' => 'linkedin', 'name' => 'LI']);

        $this->actingAs($user)
            ->get(route('workspaces.channels.connect', [$workspace->id, 'facebook']))
            ->assertSessionHasErrors('plan');
    }

    public function test_monthly_post_limit_blocks_scheduling(): void
    {
        config(['plans.free.limits.monthly_posts' => 1]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $workspace = Workspace::create(['team_id' => $team->id, 'name' => 'W']);
        $channel = $workspace->channels()->create(['provider' => 'linkedin', 'name' => 'LI']);

        Post::create(['workspace_id' => $workspace->id, 'content' => 'one', 'status' => Post::STATUS_DRAFT]);

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'workspace_id' => $workspace->id,
                'content' => 'two',
                'channel_ids' => [$channel->id],
            ])
            ->assertSessionHasErrors('plan');
    }

    public function test_higher_plans_grant_larger_limits(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $usage = app(UsageService::class);

        // Agency: 20 workspaces — allowed up to the limit, blocked beyond it.
        // (2.0 raised this from 15; no customer loses an entitlement, and
        // PlanService only ever raises a limit via team overrides.)
        $team->update(['plan' => 'agency']);
        $this->assertTrue($usage->allows($team, 'workspaces', 20));
        $this->assertFalse($usage->allows($team, 'workspaces', 21));

        // Scale: 50 workspaces.
        $team->update(['plan' => 'scale']);
        $this->assertTrue($usage->allows($team, 'workspaces', 50));
        $this->assertFalse($usage->allows($team, 'workspaces', 51));
    }

    public function test_plan_service_feature_helper_reads_boolean_flags(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $plans = app(PlanService::class);

        $this->assertFalse($plans->feature($team, 'ai_agents'));

        $team->update(['plan' => 'pro']);
        $this->assertTrue($plans->feature($team, 'ai_agents'));
        $this->assertFalse($plans->feature($team, 'not_a_real_flag'));
    }
}
