<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\ChannelHealthEvent;
use App\Models\User;
use App\Notifications\ChannelNeedsAttention;
use App\Services\ChannelHealthService;
use App\Social\ProviderManager;
use App\Social\Providers\AbstractOAuthProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Re-evaluates every connected profile's health.
 *
 * The point is to catch an expiring token *before* it breaks a scheduled
 * publish. Notifying on the transition — rather than on every run — is what
 * stops this becoming a daily nag.
 *
 * Also the one and only place `SocialProvider::refreshToken()` is ever
 * called: every OAuth driver implements it, but nothing invoked it before
 * this — a token would silently expire and force a full reconnect instead
 * of renewing itself, despite the refresh token sitting in the database for
 * exactly that purpose. Refreshing here, before evaluate() runs, means a
 * successful renewal is reflected in the very same check.
 */
class CheckChannelHealth extends Command
{
    protected $signature = 'channels:check-health';

    protected $description = 'Re-evaluate connected social profiles and warn about expiring connections';

    public function handle(ChannelHealthService $health, ProviderManager $providers): int
    {
        $checked = 0;
        $refreshed = 0;
        $newlyDegraded = [];

        Channel::with('workspace.team.owner')
            ->chunkById(200, function ($channels) use ($health, $providers, &$checked, &$refreshed, &$newlyDegraded) {
                foreach ($channels as $channel) {
                    $before = $channel->health_state;

                    if ($this->maybeRefresh($channel, $providers)) {
                        $refreshed++;
                    }

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
                Notification::send($owner, new ChannelNeedsAttention($channel));
            }
        }

        $this->info(sprintf(
            'Checked %d profile(s); %d renewed; %d newly need attention.',
            $checked,
            $refreshed,
            count($newlyDegraded),
        ));

        return self::SUCCESS;
    }

    /**
     * Proactively renew a token that is expiring soon (or already expired)
     * and has a refresh token to renew it with. Best-effort: a failed
     * refresh leaves the stored (stale) expiry in place, so evaluate()
     * still correctly flags the channel afterward exactly as it would have
     * without this step — refreshing must never make a real problem harder
     * to see.
     */
    protected function maybeRefresh(Channel $channel, ProviderManager $providers): bool
    {
        if (! $channel->refresh_token || ! $channel->token_expires_at) {
            return false;
        }

        if ($channel->token_expires_at->isAfter(now()->addDays(ChannelHealthService::EXPIRY_WARNING_DAYS))) {
            return false; // not due for renewal yet — avoid hitting every provider's token endpoint hourly.
        }

        try {
            $driver = $providers->driver($channel->provider);
        } catch (Throwable) {
            return false; // an unknown/removed provider key — nothing to refresh with.
        }

        if (! $driver instanceof AbstractOAuthProvider) {
            return false; // token/credential-based providers don't refresh via OAuth.
        }

        $expiredBefore = $channel->token_expires_at;

        try {
            $driver->refreshToken($channel);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        $channel->refresh();

        return $channel->token_expires_at && $channel->token_expires_at->isAfter($expiredBefore);
    }
}
