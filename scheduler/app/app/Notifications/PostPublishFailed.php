<?php

namespace App\Notifications;

use App\Models\PostTarget;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PostPublishFailed extends Notification
{
    use Queueable;

    public function __construct(public PostTarget $target) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('A scheduled post failed to publish')
            ->line("Publishing to channel \"{$this->target->channel->name}\" failed.")
            ->line("Error: {$this->target->error}")
            ->action('Review failures', url('/posts'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'publish_failed',
            'post_id'    => $this->target->post_id,
            'channel_id' => $this->target->channel_id,
            'message'    => "Publishing to {$this->target->channel->name} failed.",
        ];
    }
}
