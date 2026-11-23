<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent from the platform settings page to prove the SMTP details work.
 * Sent straight away (not queued), so a mistake shows up on the page.
 */
class SmtpTestMail extends Mailable
{
    use Queueable;

    public function __construct(public string $sentBy) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Ta’aleem email is working');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>This test email was sent from the Ta’aleem platform settings by '.e($this->sentBy).'.</p>'
            .'<p>Invitations, password resets and fee reminders for every school will go out through this account.</p>');
    }
}
