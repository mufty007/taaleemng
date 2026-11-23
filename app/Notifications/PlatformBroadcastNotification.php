<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A platform broadcast, also emailed to school admins.
 */
class PlatformBroadcastNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $title, public string $body) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Ta’aleem: {$this->title}")
            ->greeting('Hello,')
            ->line($this->body)
            ->action('Open Ta’aleem', route('app'));
    }
}
