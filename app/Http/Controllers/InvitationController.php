<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\School;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The page behind an invitation email: choose a password, then straight in.
 */
class InvitationController extends Controller
{
    use PasswordValidationRules;

    public function show(Request $request, User $user): View
    {
        if (! $this->isFresh($request, $user)) {
            return view('auth.invitation-used');
        }

        return view('auth.invitation', [
            'user' => $user,
            'school' => School::query()->where('slug', (string) $request->query('for'))->first(),
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse|View
    {
        if (! $this->isFresh($request, $user)) {
            return view('auth.invitation-used');
        }

        $request->validate(['password' => $this->passwordRules()]);

        $user->forceFill([
            'password' => $request->string('password')->value(),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();
        activity('auth')->causedBy($user)->log('set a password from an invitation');

        $school = School::query()->where('slug', (string) $request->query('for'))->first();

        return redirect($school && $user->canAccessSchool($school) ? route('school.dashboard', $school) : '/app')
            ->with('status', 'Welcome to Ta’aleem. Your password is set.');
    }

    private function isFresh(Request $request, User $user): bool
    {
        return hash_equals(AccountInvitation::fingerprint($user), (string) $request->query('check'));
    }
}
