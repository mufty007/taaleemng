<?php

namespace App\Http\Controllers\Platform;

use App\Actions\People\SendInvitation;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * Help a school member who can't sign in: someone who never set a password gets a fresh
 * invitation; anyone else gets a password-reset link.
 */
class SchoolMemberController extends Controller
{
    public function signInLink(Request $request, School $school, User $user, CurrentSchool $currentSchool, SendInvitation $sendInvitation): RedirectResponse
    {
        $back = redirect()->to(route('platform.schools.show', $school).'#people');

        if (blank($user->email)) {
            return $back->with('status', "{$user->name} has no email address. Their school can give them their login in person.");
        }

        if ($user->last_login_at === null) {
            $currentSchool->run($school, fn () => $sendInvitation->handle($user, $school, $request->user()));
            $sent = 'an invitation to choose a password';
        } else {
            Password::broker()->sendResetLink(['email' => $user->email]);
            $sent = 'a password-reset link';
        }

        activity('platform')->performedOn($user)
            ->withProperties(['school_id' => $school->id])
            ->log("sent {$user->name} {$sent} ({$school->name})");

        return $back->with('status', "Sent {$user->name} {$sent} at {$user->email}.");
    }
}
