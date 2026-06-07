<?php

namespace App\Console\Commands;

use App\Models\Feed;
use App\Services\RssIngestService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('feeds:poll')]
#[Description('Poll active RSS/WordPress feeds and create draft posts for new items.')]
class PollFeeds extends Command
{
    public function handle(RssIngestService $ingest): int
    {
        $created = 0;

        foreach (Feed::where('active', true)->get() as $feed) {
            $created += $ingest->ingest($feed);
        }

        $this->info("Created {$created} draft post(s) from feeds.");

        return self::SUCCESS;
    }
}
