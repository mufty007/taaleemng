<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "School fees are outstanding": sent to a parent's email with the pay link.
 *
 * Plain values only, so the queue worker needs no school context to send it.
 */
class FeeReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $schoolName,
        public string $studentName,
        public string $amount,
        public string $payLink,
        public ?string $parentName,
        public bool $canPayOnline,
        public int $linkDays,
        public ?string $schoolEmail = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("School fees reminder: {$this->studentName}")
            ->greeting($this->parentName ? "Dear {$this->parentName}," : 'Dear Parent,')
            ->line("This is a reminder from {$this->schoolName} that {$this->studentName}’s school fees of {$this->amount} are outstanding.")
            ->action($this->canPayOnline ? "Pay {$this->amount} now" : 'See the fees', $this->payLink)
            ->line($this->canPayOnline
                ? 'You can pay by card, bank transfer or USSD through Paystack. The school sees your payment straight away and your receipt is emailed to you.'
                : 'The link shows each invoice and how to pay at the school.')
            ->line("The link works for {$this->linkDays} days. If you have already paid, thank you, and please ignore this email.")
            ->salutation("{$this->schoolName} bursary");

        // Every school sends through the platform's one email account; replies go to the school.
        if (filled($this->schoolEmail)) {
            $mail->replyTo((string) $this->schoolEmail, $this->schoolName);
        }

        return $mail;
    }
}
