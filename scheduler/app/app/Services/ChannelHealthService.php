<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\ChannelHealthEvent;
use Illuminate\Support\Collection;

/**
 * Tracks whether a connected profile can actually publish.
 *
 * The engine already knows when a token expires or a driver returns 401 — what
 * was missing was somewhere to put that knowledge. `record()` is the single
 * writer; the health page and the pre-publish check both read the result.
 *
 * Events are append-only: recording the same open state twice does not create
 * a second row, and recovering stamps `resolved_at` on the open one rather
 * than deleting it, so the timeline survives.
 */
class ChannelHealthService
{
    /** How long before expiry a token counts as "expiring". */
    public const EXPIRY_WARNING_DAYS = 7;

    /**
     * Record an observation about a channel.
     *
     * @param  array<string, mixed>  $context
     */
    public function record(Channel $channel, string $state, ?string $message = null, array $context = []): ChannelHealthEvent
    {
        $severity = $this->severityFor($state);

        // Re-observing an already-open problem refreshes it rather than
        // stacking duplicates — otherwise a failing hourly sync would write
        // 24 identical rows a day.
        $open = $channel->healthEvents()
            ->where('state', $state)
            ->whereNull('resolved_at')
            ->latest('detected_at')
            ->first();

        if ($open) {
            $open->forceFill([
                'message' => $message ?? $open->message,
                'context' => $context ?: $open->context,
            ])->save();

            $event = $open;
        } else {
            $event = $channel->healthEvents()->create([
                'state'       => $state,
                'severity'    => $severity,
                'message'     => $message,
                'context'     => $context ?: null,
                'detected_at' => now(),
            ]);
        }

        $channel->forceFill([
            'health_state'         => $state,
            'last_health_check_at' => now(),
        ])->save();

        return $event;
    }

    /** Mark a channel healthy again and close any open problems. */
    public function recover(Channel $channel): void
    {
        $channel->healthEvents()
            ->whereNull('resolved_at')
            ->where('state', '!=', ChannelHealthEvent::STATE_CONNECTED)
            ->update(['resolved_at' => now()]);

        $channel->forceFill([
            'health_state'         => ChannelHealthEvent::STATE_CONNECTED,
            'last_health_check_at' => now(),
        ])->save();
    }

    /**
     * Re-derive a channel's state from what we can see without calling the
     * provider: token expiry. Run by the scheduler so an expiring connection
     * is flagged before it breaks a publish, not after.
     */
    public function evaluate(Channel $channel): string
    {
        $expiresAt = $channel->token_expires_at;

        if ($expiresAt === null) {
            // Long-lived or non-expiring credential (app password, API token).
            return $channel->health_state ?? ChannelHealthEvent::STATE_CONNECTED;
        }

        if ($expiresAt->isPast()) {
            $this->record(
                $channel,
                ChannelHealthEvent::STATE_TOKEN_EXPIRED,
                'The access token for this profile has expired. Reconnect to resume publishing.',
                ['expired_at' => $expiresAt->toIso8601String()],
            );

            return ChannelHealthEvent::STATE_TOKEN_EXPIRED;
        }

        if ($expiresAt->diffInDays(now()) <= self::EXPIRY_WARNING_DAYS) {
            $this->record(
                $channel,
                ChannelHealthEvent::STATE_TOKEN_EXPIRING,
                sprintf('This connection expires %s. Reconnect to avoid a failed publish.', $expiresAt->diffForHumans()),
                ['expires_at' => $expiresAt->toIso8601String()],
            );

            return ChannelHealthEvent::STATE_TOKEN_EXPIRING;
        }

        if ($channel->health_state !== ChannelHealthEvent::STATE_CONNECTED) {
            $this->recover($channel);
        }

        return ChannelHealthEvent::STATE_CONNECTED;
    }

    /**
     * Aggregate counts for the health page and the brand switcher badge.
     *
     * @param  Collection<int, Channel>  $channels
     * @return array<string, int>
     */
    public function summarize(Collection $channels): array
    {
        $summary = ['healthy' => 0, 'warning' => 0, 'blocked' => 0];

        foreach ($channels as $channel) {
            $state = $channel->health_state ?? ChannelHealthEvent::STATE_CONNECTED;

            $bucket = match (true) {
                in_array($state, ChannelHealthEvent::BLOCKING_STATES, true) => 'blocked',
                $state === ChannelHealthEvent::STATE_CONNECTED             => 'healthy',
                default                                                    => 'warning',
            };

            $summary[$bucket]++;
        }

        return $summary;
    }

    protected function severityFor(string $state): string
    {
        return match ($state) {
            ChannelHealthEvent::STATE_CONNECTED => 'info',
            ChannelHealthEvent::STATE_TOKEN_EXPIRED,
            ChannelHealthEvent::STATE_PERMISSION_MISSING => 'critical',
            default => 'warning',
        };
    }
}
