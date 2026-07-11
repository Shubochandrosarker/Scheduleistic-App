<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostAiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Post} */
    private function proTeamPostSetup(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        $team->update(['plan' => 'pro']);
        $workspace = Workspace::create(['team_id' => $team->id, 'name' => 'Acme']);
        $post = Post::create(['workspace_id' => $workspace->id, 'content' => 'messy   copy', 'status' => Post::STATUS_DRAFT]);

        return [$user, $post];
    }

    public function test_rewrite_endpoint_requires_the_ai_agents_plan_feature(): void
    {
        $user = User::factory()->withPersonalTeam()->create(); // free plan
        $workspace = Workspace::create(['team_id' => $user->currentTeam->id, 'name' => 'Acme']);
        $post = Post::create(['workspace_id' => $workspace->id, 'content' => 'hi', 'status' => Post::STATUS_DRAFT]);

        $this->actingAs($user)
            ->postJson(route('posts.ai.rewrite', $post))
            ->assertStatus(402);
    }

    public function test_rewrite_endpoint_rewrites_and_returns_the_post_content(): void
    {
        config(['ai.fake' => true]);
        [$user, $post] = $this->proTeamPostSetup();

        $this->actingAs($user)
            ->postJson(route('posts.ai.rewrite', $post))
            ->assertOk()
            ->assertJson(['applied' => true]);

        $this->assertSame('messy copy', $post->fresh()->content);
    }

    public function test_hashtags_endpoint_writes_hashtags(): void
    {
        config(['ai.fake' => true]);
        [$user, $post] = $this->proTeamPostSetup();
        $post->update(['content' => 'Announcing our biggest launch yet']);

        $this->actingAs($user)
            ->postJson(route('posts.ai.hashtags', $post))
            ->assertOk()
            ->assertJson(['applied' => true]);

        $this->assertNotEmpty($post->fresh()->hashtags);
    }

    public function test_quality_endpoint_writes_a_report(): void
    {
        config(['ai.fake' => true]);
        [$user, $post] = $this->proTeamPostSetup();

        $this->actingAs($user)
            ->postJson(route('posts.ai.quality', $post))
            ->assertOk();

        $this->assertNotNull($post->fresh()->ai_quality_report);
    }

    public function test_a_user_outside_the_workspace_cannot_run_agents_on_its_posts(): void
    {
        [, $post] = $this->proTeamPostSetup();
        $outsider = User::factory()->withPersonalTeam()->create();
        $outsider->currentTeam->update(['plan' => 'pro']);

        $this->actingAs($outsider)
            ->postJson(route('posts.ai.rewrite', $post))
            ->assertStatus(403);
    }
}
