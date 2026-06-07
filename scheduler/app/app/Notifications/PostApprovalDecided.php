<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PostApprovalDecided extends Notification
{
    use Queueable;

    public function __construct(public Post $post, public string $decision, public ?string $comment = null) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Your post was {$this->decision}")
            ->line("Your post in \"{$this->post->workspace->name}\" was {$this->decision}.");

        if ($this->comment) {
            $mail->line("Reviewer note: {$this->comment}");
        }

        return $mail->action('Open post', url('/posts'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type'     => 'approval_decided',
            'post_id'  => $this->post->id,
            'decision' => $this->decision,
            'message'  => "Your post was {$this->decision}.",
        ];
    }
}
