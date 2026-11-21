<?php

namespace App\Actions\People;

use App\Models\School;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Validation\ValidationException;

/**
 * Email someone a link to choose their own password, instead of handing over a
 * temporary one. Used for staff and parents who have an email address.
 */
class SendInvitation
{
    public function handle(User $user, School $school, User $sender): void
    {
        if (blank($user->email)) {
            throw ValidationException::withMessages(['email' => "{$user->name} has no email address. Add one, or give them their login in person."]);
        }

        $user->notify(new AccountInvitation($school, $sender->name));

        activity('people')->performedOn($user)->log("emailed {$user->name} a sign-in link");
    }
}
