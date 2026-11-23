<?php

namespace App\Http\Controllers\Platform;

use App\Enums\SchoolRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

/**
 * Support tools: find anyone on the platform, see their schools and roles, help them
 * sign in again, and manage who is a super admin.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $admins = $request->query('who') === 'admins';

        $users = User::query()
            ->withCount('activeSchools')
            ->when($admins, fn ($query) => $query->where('is_platform_admin', true))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', '%'.(User::normalisePhone($search) ?? $search).'%')))
            ->orderByDesc('is_platform_admin')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('platform.users.index', [
            'users' => $users,
            'search' => $search,
            'admins' => $admins,
            'adminCount' => User::query()->where('is_platform_admin', true)->count(),
            'total' => User::query()->count(),
        ]);
    }

    public function show(User $user): View
    {
        $user->load(['schools' => fn ($query) => $query->orderBy('name')]);

        return view('platform.users.show', [
            'person' => $user,
            'rolesBySchool' => $this->rolesBySchool($user),
        ]);
    }

    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        if (blank($user->email)) {
            return back()->with('status', "{$user->name} has no email address. Their school can reset their password from Users & roles.");
        }

        Password::broker()->sendResetLink(['email' => $user->email]);
        activity('platform')->causedBy($request->user())->performedOn($user)->log("sent {$user->name} a password-reset link");

        return back()->with('status', "Password-reset link sent to {$user->email}.");
    }

    /**
     * For someone who lost their phone: turn two-step sign-in off so they can get back in.
     */
    public function disableTwoFactor(Request $request, User $user, DisableTwoFactorAuthentication $disable): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'Turn off your own two-step sign-in from Account & security.');

        $disable($user);
        activity('platform')->causedBy($request->user())->performedOn($user)->log("turned off two-step sign-in for {$user->name}");

        return back()->with('status', "Two-step sign-in is off for {$user->name}. Ask them to turn it on again from Account & security.");
    }

    /**
     * Make someone a super admin, creating their login if they don't have one yet.
     */
    public function storeAdmin(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('admin', [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
        ]);
        $email = Str::lower($data['email']);

        $user = User::query()->where('email', $email)->first()
            ?? User::query()->create(['name' => $data['name'], 'email' => $email, 'password' => Str::password(32)]);
        $user->forceFill(['is_platform_admin' => true, 'email_verified_at' => $user->email_verified_at ?? now()])->save();

        Password::broker()->sendResetLink(['email' => $email]);
        activity('platform')->causedBy($request->user())->performedOn($user)->log("made {$user->name} a super admin");

        return to_route('platform.users.index', ['who' => 'admins'])->with('status', "{$user->name} is now a super admin. We emailed {$email} a link to set a password.");
    }

    public function removeAdmin(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'You can’t remove yourself as a super admin.');
        abort_if(User::query()->where('is_platform_admin', true)->count() <= 1, 422, 'Keep at least one super admin.');

        $user->forceFill(['is_platform_admin' => false])->save();
        activity('platform')->causedBy($request->user())->performedOn($user)->log("removed {$user->name} as a super admin");

        return back()->with('status', "{$user->name} is no longer a super admin.");
    }

    /**
     * @return Collection<int|string, Collection<int, SchoolRole>>
     */
    private function rolesBySchool(User $user): Collection
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->where('model_has_roles.model_id', $user->id)
            ->get(['model_has_roles.school_id', 'roles.name'])
            ->groupBy('school_id')
            ->map(fn (Collection $rows): Collection => $rows->map(fn (object $row): ?SchoolRole => SchoolRole::tryFrom($row->name))->filter()->values());
    }
}
