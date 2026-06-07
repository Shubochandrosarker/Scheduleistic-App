<?php

namespace App\Console\Commands;

use App\Jobs\FetchMetricsJob;
use App\Models\PostTarget;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('analytics:fetch')]
#[Description('Queue metric captures for recently published post targets.')]
class FetchAnalytics extends Command
{
    public function handle(): int
    {
        $targets = PostTarget::query()
            ->where('status', PostTarget::STATUS_PUBLISHED)
            ->whereNotNull('provider_post_id')
            ->where('published_at', '>=', now()->subDays(30))
            ->get();

        foreach ($targets as $target) {
            FetchMetricsJob::dispatch($target);
        }

        $this->info("Queued metric captures for {$targets->count()} target(s).");

        return self::SUCCESS;
    }
}
