<?php

namespace App\Support\Platform;

use App\Enums\SchoolRole;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A super admin "views as" someone in a school to see exactly what they see.
 *
 * The admin's id is kept in the session and they are signed in as the other person.
 * Account settings and chat are closed while viewing as (see HandleViewAs), nothing
 * counts as that person's own sign-in, and both the platform and the school log it.
 */
final class ViewAs
{
    public const ADMIN = 'view_as.admin_id';

    public const SCHOOL = 'view_as.school_id';

    /** Set while switching back, so the sign-in listener doesn't count it as a login. */
    public const RETURNING = 'view_as.returning';

    public static function active(?Request $request = null): bool
    {
        $request ??= request();

        return $request->hasSession() && $request->session()->has(self::ADMIN);
    }

    /**
     * Whether a sign-in happening now is a switch into or out of "view as".
     */
    public static function switching(?Request $request = null): bool
    {
        $request ??= request();

        return $request->hasSession() && ($request->session()->has(self::ADMIN) || $request->session()->has(self::RETURNING));
    }

    public static function admin(): ?User
    {
        return self::active() ? User::query()->find(request()->session()->get(self::ADMIN)) : null;
    }

    public static function start(Request $request, User $admin, School $school, User $target): RedirectResponse
    {
        $isStaff = app(CurrentSchool::class)->run($school, function () use ($admin, $school, $target): bool {
            activity('support')->causedBy($admin)->performedOn($target)->log("Ta’aleem support started viewing {$school->name} as {$target->name}");

            return $target->isStaffInCurrentSchool();
        });

        activity('platform')->causedBy($admin)->performedOn($target)
            ->withProperties(['school_id' => $school->id, 'school' => $school->name])
            ->log("viewed {$school->name} as {$target->name}");

        $request->session()->put(self::ADMIN, $admin->id);
        $request->session()->put(self::SCHOOL, $school->id);

        Auth::guard('web')->login($target);
        $request->session()->regenerate();

        return redirect()->route($isStaff ? 'school.dashboard' : 'school.portal', $school)
            ->with('status', "You are viewing {$school->name} as {$target->name}. Stop from the red bar at the top.");
    }

    public static function stop(Request $request): RedirectResponse
    {
        $admin = User::query()->find($request->session()->get(self::ADMIN));
        $school = School::query()->find($request->session()->get(self::SCHOOL));

        $request->session()->forget([self::ADMIN, self::SCHOOL]);

        if ($admin === null || ! $admin->is_platform_admin) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('login');
        }

        $request->session()->put(self::RETURNING, true);
        Auth::guard('web')->login($admin);
        $request->session()->forget(self::RETURNING);
        $request->session()->regenerate();

        return $school
            ? redirect()->route('platform.schools.show', $school)->with('status', 'Back in the platform console.')
            : redirect()->route('platform.dashboard');
    }

    /**
     * "Funke Adebayo (Principal)" for the banner.
     */
    public static function describe(User $user): string
    {
        $role = $user->getRoleNames()
            ->map(fn (string $name): ?SchoolRole => SchoolRole::tryFrom($name))
            ->filter()
            ->map(fn (SchoolRole $role): string => $role->label())
            ->first();

        return $role ? "{$user->name} ({$role})" : $user->name;
    }
}
