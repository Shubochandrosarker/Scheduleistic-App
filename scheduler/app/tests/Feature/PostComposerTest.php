<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostTarget;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PostComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PostComposerTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceWithChannels(int $count = 2): Workspace
    {
        $user = User::factory()->withPersonalTeam()->create();

        $workspace = Workspace::create([
            'team_id' => $user->currentTeam->id,
            'name'    => 'Acme',
        ]);

        foreach (['linkedin', 'facebook'] as $i => $provider) {
            if ($i >= $count) {
                break;
            }
            $workspace->channels()->create([
                'provider' => $provider,
                'name'     => "Acme {$provider}",
            ]);
        }

        return $workspace;
    }

    public function test_it_creates_a_scheduled_post_with_one_target_per_channel(): void
    {
        $workspace = $this->workspaceWithChannels(2);
        $when = Carbon::now()->addHour();

        $post = app(PostComposer::class)->schedule(
            workspace: $workspace,
            content: 'Hello world',
            channelIds: $workspace->channels->pluck('id')->all(),
            scheduledAt: $when,
        );

        $this->assertSame(Post::STATUS_SCHEDULED, $post->status);
        $this->assertCount(2, $post->targets);
        $this->assertTrue($post->targets->every(fn ($t) => $t->status === PostTarget::STATUS_PENDING));
        $this->assertNotNull($post->group_id);
    }

    public function test_a_post_with_no_schedule_is_a_draft(): void
    {
        $workspace = $this->workspaceWithChannels(1);

        $post = app(PostComposer::class)->schedule(
            workspace: $workspace,
            content: 'Draft me',
            channelIds: $workspace->channels->pluck('id')->all(),
        );

        $this->assertSame(Post::STATUS_DRAFT, $post->status);
    }

    public function test_per_channel_overrides_create_versions(): void
    {
        $workspace = $this->workspaceWithChannels(2);

        $post = app(PostComposer::class)->schedule(
            workspace: $workspace,
            content: 'Base content',
            channelIds: $workspace->channels->pluck('id')->all(),
            overrides: ['linkedin' => ['content' => 'LinkedIn-specific copy']],
        );

        $this->assertSame('LinkedIn-specific copy', $post->contentFor('linkedin'));
        $this->assertSame('Base content', $post->contentFor('facebook'));
    }
}
