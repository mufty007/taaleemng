<?php

namespace App\Http\Controllers\School;

use App\Actions\People\SendInvitation;
use App\Enums\MembershipStatus;
use App\Enums\SchoolRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AccountInvitation;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Users & roles": everyone who can sign in to this school, and their roles here.
 */
class MemberController extends Controller
{
    public function index(Request $request, CurrentSchool $currentSchool): View
    {
        $school = $currentSchool->getOrFail();
        $role = SchoolRole::tryFrom((string) $request->query('role'));
        $search = trim((string) $request->query('search'));

        $rolesByUser = DB::table(config('permission.table_names.model_has_roles'))
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.school_id', $school->id)
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->get(['model_has_roles.model_id', 'roles.name'])
            ->groupBy('model_id')
            ->map(fn ($rows) => $rows->pluck('name')->all());

        $members = $school->users()
            ->when($role, fn ($query) => $query->whereIn('users.id', $rolesByUser->filter(fn ($names) => in_array($role->value, $names, true))->keys()))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('users.name', 'like', "%{$search}%")
                ->orWhere('users.email', 'like', "%{$search}%")
                ->orWhere('users.phone', 'like', "%{$search}%")))
            ->orderBy('users.name')
            ->paginate(30)
            ->withQueryString();

        $roleCounts = collect(SchoolRole::cases())->mapWithKeys(fn (SchoolRole $schoolRole) => [
            $schoolRole->value => $rolesByUser->filter(fn ($names) => in_array($schoolRole->value, $names, true))->count(),
        ]);

        return view('school.members.index', [
            'members' => $members,
            'rolesByUser' => $rolesByUser,
            'roleCounts' => $roleCounts,
            'selectedRole' => $role,
            'search' => $search,
        ]);
    }

    /**
     * Email a member a link to choose their own password.
     */
    public function invite(Request $request, CurrentSchool $currentSchool, User $user, SendInvitation $sendInvitation): RedirectResponse
    {
        $school = $currentSchool->getOrFail();
        $school->users()->whereKey($user->id)->firstOrFail();

        $sendInvitation->handle($user, $school, $request->user());

        return back()->with('status', "A sign-in link is on its way to {$user->email}. It works once and expires in ".AccountInvitation::DAYS_VALID.' days.');
    }

    public function toggle(Request $request, CurrentSchool $currentSchool, User $user): RedirectResponse
    {
        $school = $currentSchool->getOrFail();
        abort_if($user->is($request->user()), 422, 'You cannot disable your own access.');

        $membership = $school->users()->whereKey($user->id)->firstOrFail()->membership;
        $disabling = $membership->status === MembershipStatus::Active;

        $school->users()->updateExistingPivot($user->id, [
            'status' => $disabling ? MembershipStatus::Disabled : MembershipStatus::Active,
        ]);

        activity('people')->performedOn($user)->log(($disabling ? 'disabled ' : 'restored ').$user->name.'’s access');

        return back()->with('status', $disabling
            ? "{$user->name} can no longer sign in to {$school->name}. Their records are kept."
            : "{$user->name} can sign in again.");
    }
}
