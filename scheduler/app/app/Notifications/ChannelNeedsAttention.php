<?php

namespace App\Notifications;

use App\Models\Channel;
use App\Models\ChannelHealthEvent;
use App\Models\NotificationPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A connected profile changed to a state that will stop it publishing, or is
 * about to. Sent on the transition only — see CheckChannelHealth.
 *
 * Channels are resolved from the recipient's stored preferences rather than
 * hard-coded, so "email me about connection problems, but not in the digest"
 * is honoured.
 */
class ChannelNeedsAttention extends Notification
{
    use Queueable;

    public function __construct(public Channel $channel) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $preference = NotificationPreference::resolve($notifiable, 'channel_expiring');

        return array_values(array_filter([
            $preference['in_app'] ? 'database' : null,
            $preference['email'] && $preference['digest'] === 'immediate' ? 'mail' : null,
        ]));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A social profile needs reconnecting')
            ->line("\"{$this->channel->name}\" ({$this->channel->provider}) {$this->summary()}")
            ->line('Scheduled posts to this profile will fail until it is reconnected.')
            ->action('Open profile health', url('/social-profiles/health'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'channel_expiring',
            'channel_id' => $this->channel->id,
            'provider'   => $this->channel->provider,
            'state'      => $this->channel->health_state,
            'message'    => "\"{$this->channel->name}\" {$this->summary()}",
        ];
    }

    protected function summary(): string
    {
        return match ($this->channel->health_state) {
            ChannelHealthEvent::STATE_TOKEN_EXPIRED     => 'has expired and can no longer publish.',
            ChannelHealthEvent::STATE_TOKEN_EXPIRING    => 'expires soon and should be reconnected.',
            ChannelHealthEvent::STATE_PERMISSION_MISSING => 'is missing a permission it needs to publish.',
            ChannelHealthEvent::STATE_RATE_LIMITED      => 'is currently rate limited by the network.',
            default                                      => 'needs attention.',
        };
    }
}
