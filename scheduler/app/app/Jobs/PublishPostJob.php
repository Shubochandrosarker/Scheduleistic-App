<?php

namespace App\Jobs;

use App\Models\PostTarget;
use App\Social\Data\PublishPayload;
use App\Social\Exceptions\PublishException;
use App\Social\ProviderManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Publishes a single PostTarget (one post → one channel). The engine creates
 * one job per target so each network's outcome is independent. Retries with
 * backoff on retryable failures; respects a per-channel rate limit.
 */
class PublishPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public PostTarget $target) {}

    /** Backoff between retries (seconds). */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(ProviderManager $manager): void
    {
        $target = $this->target->fresh(['channel', 'post.versions']);

        if (! $target || $target->status === PostTarget::STATUS_PUBLISHED) {
            return; // idempotent: already done or gone.
        }

        $channel  = $target->channel;
        $post     = $target->post;
        $provider = $manager->driver($channel->provider);

        // Per-channel rate limiting.
        $limit = $provider->rateLimit();
        $key   = "publish:channel:{$channel->id}";

        if (RateLimiter::tooManyAttempts($key, $limit['max'])) {
            $this->release(RateLimiter::availableIn($key) ?: 5);

            return;
        }
        RateLimiter::hit($key, $limit['per_seconds']);

        $target->update([
            'status'   => PostTarget::STATUS_PUBLISHING,
            'attempts' => $target->attempts + 1,
        ]);

        $payload = new PublishPayload(
            content: $post->contentFor($channel->provider) ?? '',
            media: $post->media ?? [],
            options: optional($post->versions->firstWhere('provider', $channel->provider))->options ?? [],
        );

        try {
            $result = $provider->publish($channel, $payload);
        } catch (PublishException $e) {
            $target->update(['error' => $e->getMessage()]);

            // Give up immediately on non-retryable errors, or once retries are spent.
            if (! $e->retryable || $this->attempts() >= $this->tries) {
                $this->markFailed($e->getMessage());

                return;
            }

            $this->release($e->retryAfter ?? $this->backoff()[$this->attempts() - 1] ?? 60);

            return;
        }

        $target->update([
            'status'           => PostTarget::STATUS_PUBLISHED,
            'provider_post_id' => $result->providerPostId,
            'error'            => null,
            'published_at'     => now(),
        ]);

        $post->syncStatusFromTargets();

        // When a recurring post is fully published, queue its next occurrence.
        $fresh = $post->fresh();
        if ($fresh && $fresh->status === \App\Models\Post::STATUS_PUBLISHED && $fresh->recurring_rule) {
            app(\App\Services\RecurrenceService::class)->createNextOccurrence($fresh);
        }
    }

    /** Called by the queue when the job has exhausted its retries. */
    public function failed(\Throwable $e): void
    {
        $this->markFailed($e->getMessage());
    }

    protected function markFailed(string $message): void
    {
        $target = $this->target->fresh();

        if (! $target || $target->status === PostTarget::STATUS_PUBLISHED) {
            return;
        }

        $target->update([
            'status' => PostTarget::STATUS_FAILED,
            'error'  => $message,
        ]);

        $target->post->syncStatusFromTargets();

        // Alert the author so failures never pass silently.
        $target->loadMissing('channel', 'post.author');
        $target->post->author?->notify(new \App\Notifications\PostPublishFailed($target));
    }
}
