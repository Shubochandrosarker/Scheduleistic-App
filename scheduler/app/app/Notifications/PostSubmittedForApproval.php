<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PostSubmittedForApproval extends Notification
{
    use Queueable;

    public function __construct(public Post $post) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A post needs your approval')
            ->line("A post in workspace \"{$this->post->workspace->name}\" is awaiting review.")
            ->action('Review post', url('/posts'))
            ->line('Thank you for using '.config('scheduleistic.name').'.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'post_submitted',
            'post_id'      => $this->post->id,
            'workspace_id' => $this->post->workspace_id,
            'message'      => 'A post is awaiting your approval.',
        ];
    }
}
