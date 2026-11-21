<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\CreateSchool;
use App\Enums\MembershipStatus;
use App\Enums\SchoolRole;
use App\Enums\SchoolStatus;
use App\Enums\SchoolType;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreSchoolRequest;
use App\Models\ClassArm;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Term;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class SchoolController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $schools = School::query()
            ->withCount(['users' => fn ($query) => $query->where('school_user.status', MembershipStatus::Active->value)])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
                ->orWhere('state', 'like', "%{$search}%")))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('platform.schools.index', [
            'schools' => $schools,
            'search' => $search,
        ]);
    }

    public function show(School $school, CurrentSchool $currentSchool): View
    {
        $stats = $currentSchool->run($school, fn (): array => [
            ['label' => 'Students', 'value' => Student::query()->where('status', StudentStatus::Active)->count(), 'icon' => 'graduation-cap', 'tone' => 'brand'],
            ['label' => 'Staff', 'value' => Staff::query()->count(), 'icon' => 'briefcase', 'tone' => 'naija'],
            ['label' => 'Classes', 'value' => ClassArm::query()->count(), 'icon' => 'layers', 'tone' => 'sky'],
            ['label' => 'Parents', 'value' => Guardian::query()->count(), 'icon' => 'users', 'tone' => 'gold'],
        ]);

        return view('platform.schools.show', [
            'school' => $school,
            'stats' => $stats,
            'currentTerm' => $currentSchool->run($school, fn () => Term::current()),
            'admins' => $school->users()
                ->whereIn('users.id', DB::table('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('model_has_roles.school_id', $school->id)
                    ->where('roles.name', SchoolRole::SchoolAdmin->value)
                    ->pluck('model_has_roles.model_id'))
                ->get(),
            'activity' => Activity::query()->where('school_id', $school->id)->with('causer')->latest()->limit(12)->get(),
            'statuses' => SchoolStatus::cases(),
        ]);
    }

    public function create(): View
    {
        return view('platform.schools.create', [
            'schoolTypes' => SchoolType::cases(),
            'states' => config('nigeria.states'),
        ]);
    }

    public function store(StoreSchoolRequest $request, CreateSchool $createSchool): RedirectResponse
    {
        $data = $request->validated();

        $school = $createSchool->handle(
            [
                'name' => $data['name'],
                'school_type' => $data['school_type'],
                'state' => $data['state'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
            ],
            ['name' => $data['admin_name'], 'email' => $data['admin_email']],
        );

        return redirect()
            ->route('platform.schools.index')
            ->with('status', "{$school->name} is set up. We’ve emailed {$data['admin_email']} a link to choose a password.");
    }
}
