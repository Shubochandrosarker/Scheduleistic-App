<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\ChannelHealthEvent;
use App\Models\User;
use App\Services\ChannelHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Re-evaluates every connected profile's health.
 *
 * The point is to catch an expiring token *before* it breaks a scheduled
 * publish. Notifying on the transition — rather than on every run — is what
 * stops this becoming a daily nag.
 */
class CheckChannelHealth extends Command
{
    protected $signature = 'channels:check-health';

    protected $description = 'Re-evaluate connected social profiles and warn about expiring connections';

    public function handle(ChannelHealthService $health): int
    {
        $checked = 0;
        $newlyDegraded = [];

        Channel::with('workspace.team.owner')
            ->chunkById(200, function ($channels) use ($health, &$checked, &$newlyDegraded) {
                foreach ($channels as $channel) {
                    $before = $channel->health_state;
                    $after = $health->evaluate($channel);
                    $checked++;

                    // Only a change of state is worth telling anyone about.
                    if ($after !== $before && $after !== ChannelHealthEvent::STATE_CONNECTED) {
                        $newlyDegraded[] = $channel;
                    }
                }
            });

        foreach ($newlyDegraded as $channel) {
            $owner = $channel->workspace?->team?->owner;

            if ($owner instanceof User) {
                Notification::send($owner, new \App\Notifications\ChannelNeedsAttention($channel));
            }
        }

        $this->info(sprintf(
            'Checked %d profile(s); %d newly need attention.',
            $checked,
            count($newlyDegraded),
        ));

        return self::SUCCESS;
    }
}
