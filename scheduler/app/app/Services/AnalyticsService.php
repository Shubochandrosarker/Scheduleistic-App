<?php

namespace App\Services;

use App\Models\AnalyticsMetric;
use App\Models\Channel;
use App\Models\PostTarget;
use App\Models\Team;
use App\Social\ProviderManager;
use Illuminate\Support\Facades\DB;

/**
 * Captures and aggregates engagement metrics for published posts.
 */
class AnalyticsService
{
    public function __construct(private readonly ProviderManager $providers) {}

    /** Pull fresh metrics for a published target from its provider and store them. */
    public function capture(PostTarget $target): void
    {
        if ($target->status !== PostTarget::STATUS_PUBLISHED || ! $target->provider_post_id) {
            return;
        }

        $channel  = $target->channel;
        $metrics  = $this->providers->driver($channel->provider)->fetchMetrics($channel, $target->provider_post_id);

        foreach ($metrics as $metric => $value) {
            AnalyticsMetric::create([
                'post_target_id' => $target->id,
                'channel_id'     => $channel->id,
                'metric'         => $metric,
                'value'          => (int) $value,
                'captured_at'    => now(),
            ]);
        }
    }

    /**
     * Latest totals per metric for an organization (most recent capture per target).
     *
     * @return array<string, int>
     */
    public function totalsForTeam(Team $team): array
    {
        $channelIds = Channel::whereIn('workspace_id', $team->workspaces()->pluck('id'))->pluck('id');

        if ($channelIds->isEmpty()) {
            return [];
        }

        // Sum the latest value per (target, metric).
        $latest = AnalyticsMetric::query()
            ->whereIn('channel_id', $channelIds)
            ->select('metric', DB::raw('SUM(value) as total'))
            ->whereIn('id', function ($q) use ($channelIds) {
                $q->from('analytics_metrics')
                    ->select(DB::raw('MAX(id)'))
                    ->whereIn('channel_id', $channelIds)
                    ->groupBy('post_target_id', 'metric');
            })
            ->groupBy('metric')
            ->pluck('total', 'metric');

        return $latest->map(fn ($v) => (int) $v)->all();
    }
}
