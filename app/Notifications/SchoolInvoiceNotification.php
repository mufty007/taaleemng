<?php

namespace App\Notifications;

use App\Models\SchoolInvoice;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Ta'aleem's subscription invoice, emailed to a school's admins.
 */
class SchoolInvoiceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SchoolInvoice $invoice) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->invoice->loadMissing('school');

        return (new MailMessage)
            ->subject("Ta’aleem invoice {$invoice->number} for {$invoice->school->name}")
            ->greeting('Hello,')
            ->line("Here is Ta’aleem’s invoice for {$invoice->school->name}: {$invoice->period}.")
            ->line("{$invoice->students} students × ".Money::format($invoice->unit_price).' = **'.Money::format($invoice->amount).'**, due by '.$invoice->due_on->format('j F Y').'.')
            ->action('See it in Settings', route('school.settings.edit', ['school' => $invoice->school->slug]))
            ->line('Pay by bank transfer and reply to this email with the reference, or call us if you have any questions. Thank you for using Ta’aleem.');
    }
}
