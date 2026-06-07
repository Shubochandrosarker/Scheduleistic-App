<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\Post;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns composer input into a Post with per-channel versions and one target
 * per channel, ready for the scheduling engine.
 */
class PostComposer
{
    /**
     * @param  array<int, int>  $channelIds        channels to publish to
     * @param  array<string, array{content?:string, options?:array}>  $overrides  per-provider overrides
     */
    public function schedule(
        Workspace $workspace,
        string $content,
        array $channelIds,
        ?Carbon $scheduledAt = null,
        array $overrides = [],
        ?int $authorId = null,
        array $media = [],
    ): Post {
        $channels = Channel::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('id', $channelIds)
            ->get();

        if ($channels->isEmpty()) {
            throw new \InvalidArgumentException('At least one valid channel is required.');
        }

        return DB::transaction(function () use ($workspace, $content, $channels, $scheduledAt, $overrides, $authorId, $media) {
            $post = Post::create([
                'workspace_id' => $workspace->id,
                'author_id'    => $authorId,
                'status'       => $scheduledAt ? Post::STATUS_SCHEDULED : Post::STATUS_DRAFT,
                'content'      => $content,
                'media'        => $media,
                'scheduled_at' => $scheduledAt,
                'timezone'     => $workspace->timezone ?? 'UTC',
            ]);

            $this->buildVersionsAndTargets($post, $channels, $overrides);

            return $post;
        });
    }

    /**
     * @param  Collection<int, Channel>  $channels
     * @param  array<string, array{content?:string, options?:array}>  $overrides
     */
    protected function buildVersionsAndTargets(Post $post, Collection $channels, array $overrides): void
    {
        foreach ($channels as $channel) {
            $override = $overrides[$channel->provider] ?? [];

            if (isset($override['content']) || isset($override['options'])) {
                $post->versions()->create([
                    'provider' => $channel->provider,
                    'content'  => $override['content'] ?? null,
                    'options'  => $override['options'] ?? null,
                ]);
            }

            $post->targets()->create([
                'channel_id' => $channel->id,
                'status'     => \App\Models\PostTarget::STATUS_PENDING,
            ]);
        }
    }
}
