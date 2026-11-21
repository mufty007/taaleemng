<?php

namespace App\Notifications;

use App\Models\School;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * "Your school has set up your Ta'aleem account": a link to choose a password.
 *
 * The link is signed, lasts a week, and carries a fingerprint of the current
 * password, so it stops working as soon as a password is set.
 */
class AccountInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public const DAYS_VALID = 7;

    public function __construct(public School $school, public string $invitedBy) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->school->name} has set up your Ta’aleem account")
            ->greeting('Hello '.str($notifiable->name)->before(' ').',')
            ->line("{$this->invitedBy} has set up your account for {$this->school->name} on Ta’aleem, the school’s results, fees and messages system.")
            ->action('Choose your password', self::url($notifiable, $this->school))
            ->line('You can sign in with this email address'.($notifiable->phone ? ' or your phone number' : '').'. The link works once and expires in '.self::DAYS_VALID.' days.')
            ->line('If you were not expecting this, you can ignore this email.');
    }

    public static function url(User $user, School $school): string
    {
        return URL::temporarySignedRoute('invitation.show', now()->addDays(self::DAYS_VALID), [
            'user' => $user->ulid,
            'for' => $school->slug,
            'check' => self::fingerprint($user),
        ]);
    }

    /**
     * Changes whenever the password does, which retires every earlier link.
     */
    public static function fingerprint(User $user): string
    {
        return substr(hash_hmac('sha256', (string) $user->getAuthPassword(), (string) config('app.key')), 0, 20);
    }
}
