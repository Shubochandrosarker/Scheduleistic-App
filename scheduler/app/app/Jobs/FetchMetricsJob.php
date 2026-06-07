<?php

namespace App\Jobs;

use App\Models\PostTarget;
use App\Services\AnalyticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Captures engagement metrics for a single published target. */
class FetchMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public PostTarget $target) {}

    public function handle(AnalyticsService $analytics): void
    {
        $analytics->capture($this->target);
    }
}
