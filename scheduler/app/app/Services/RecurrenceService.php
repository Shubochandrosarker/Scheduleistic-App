<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostTarget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates the next occurrence of a recurring post after it publishes.
 */
class RecurrenceService
{
    /** @var array<string, callable(Carbon):Carbon> */
    private array $rules;

    public function __construct()
    {
        $this->rules = [
            'daily'   => fn (Carbon $d) => $d->copy()->addDay(),
            'weekly'  => fn (Carbon $d) => $d->copy()->addWeek(),
            'monthly' => fn (Carbon $d) => $d->copy()->addMonth(),
        ];
    }

    public function createNextOccurrence(Post $post): ?Post
    {
        $rule = $post->recurring_rule;

        if (! $rule || ! isset($this->rules[$rule]) || ! $post->scheduled_at) {
            return null;
        }

        // Only the original (parent) post spawns occurrences, and only once each.
        $root = $post->parent_post_id ? Post::find($post->parent_post_id) : $post;
        if (! $root) {
            return null;
        }

        $nextAt = $this->rules[$rule]($post->scheduled_at);

        // Idempotency: don't duplicate an occurrence already created for this slot.
        $exists = Post::where('parent_post_id', $root->id)
            ->where('scheduled_at', $nextAt)
            ->exists();

        if ($exists) {
            return null;
        }

        return DB::transaction(function () use ($post, $root, $nextAt, $rule) {
            $next = Post::create([
                'workspace_id'   => $post->workspace_id,
                'author_id'      => $post->author_id,
                'status'         => Post::STATUS_SCHEDULED,
                'content'        => $post->content,
                'media'          => $post->media,
                'scheduled_at'   => $nextAt,
                'timezone'       => $post->timezone ?? 'UTC',
                'recurring_rule' => $rule,
                'parent_post_id' => $root->id,
                'source'         => $post->source ?? 'manual',
            ]);

            foreach ($post->versions as $version) {
                $next->versions()->create([
                    'provider' => $version->provider,
                    'content'  => $version->content,
                    'media'    => $version->media,
                    'options'  => $version->options,
                ]);
            }

            foreach ($post->targets as $target) {
                $next->targets()->create([
                    'channel_id' => $target->channel_id,
                    'status'     => PostTarget::STATUS_PENDING,
                ]);
            }

            return $next;
        });
    }
}
