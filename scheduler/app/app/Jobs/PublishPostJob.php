<?php

namespace App\Jobs;

use App\Models\ChannelHealthEvent;
use App\Models\PostTarget;
use App\Services\ChannelHealthService;
use App\Services\MediaValidator;
use App\Services\WebhookDispatcher;
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
        // Resolved from the container rather than injected, so callers that
        // invoke handle() with just the provider manager (the existing engine
        // tests do) keep working.
        $validator = app(MediaValidator::class);
        $health    = app(ChannelHealthService::class);

        $target = $this->target->fresh(['channel', 'post.versions']);

        if (! $target || $target->status === PostTarget::STATUS_PUBLISHED) {
            return; // idempotent: already done or gone.
        }

        $channel  = $target->channel;
        $post     = $target->post;
        $provider = $manager->driver($channel->provider);

        // A profile whose token has expired cannot publish. Failing here with
        // a readable reason beats burning three retries on a guaranteed 401.
        if (! $channel->canPublish()) {
            $this->markFailed('This social profile needs reconnecting before it can publish.');

            return;
        }

        // Re-validate against the network's current rules. The composer already
        // checked, but a post can be edited after it was scheduled — and the
        // rules themselves can change under it.
        $errors = $validator->errors(
            $channel->provider,
            $this->describeMedia($post->media ?? []),
            $post->contentFor($channel->provider),
        );

        if ($errors !== []) {
            $this->markFailed($errors[0]['message']);

            return;
        }

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

        // A successful publish is the strongest possible health signal.
        $channel->forceFill(['last_published_at' => now()])->save();

        if ($channel->health_state !== ChannelHealthEvent::STATE_CONNECTED) {
            $health->recover($channel);
        }

        $post->syncStatusFromTargets();

        $this->notifyWebhooks('publish.succeeded', $target);

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

        // Record the failure against the channel so the health page can show
        // it without every consumer re-deriving state from post targets.
        if ($target->channel) {
            app(ChannelHealthService::class)->record(
                $target->channel,
                ChannelHealthEvent::STATE_PUBLISH_FAILED,
                $message,
                ['post_id' => $target->post_id],
            );
        }

        // Alert the author so failures never pass silently.
        $target->loadMissing('channel', 'post.author');
        $target->post->author?->notify(new \App\Notifications\PostPublishFailed($target));

        $this->notifyWebhooks('publish.failed', $target);
    }

    /**
     * Normalise the post's stored media array into the descriptor shape
     * MediaValidator expects. Media may be a bare URL string (legacy) or a
     * descriptor array; both are accepted.
     *
     * @param  array<int, mixed>  $media
     * @return array<int, array<string, mixed>>
     */
    protected function describeMedia(array $media): array
    {
        return array_values(array_map(static function ($item) {
            if (is_string($item)) {
                // A bare URL tells us nothing measurable, so only the kind is
                // inferred — validation then checks counts, not dimensions.
                return [
                    'kind' => preg_match('/\.(mp4|mov|webm)(\?|$)/i', $item) ? 'video' : 'image',
                    'name' => basename(parse_url($item, PHP_URL_PATH) ?: 'media'),
                ];
            }

            return is_array($item) ? $item : [];
        }, $media));
    }

    /** Fan the outcome out to the organization's webhook endpoints. */
    protected function notifyWebhooks(string $event, PostTarget $target): void
    {
        $team = $target->post?->workspace?->team;

        if (! $team) {
            return;
        }

        app(WebhookDispatcher::class)->dispatch($team, $event, [
            'post_id'      => $target->post_id,
            'target_id'    => $target->id,
            'workspace_id' => $target->post->workspace_id,
            'provider'     => $target->channel?->provider,
            'status'       => $target->status,
            'error'        => $target->error,
        ], $target->post->workspace_id);
    }
}
