<?php

namespace App\Http\Controllers\School;

use App\Actions\People\AddStaffMember;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\SchoolRole;
use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\User;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $staff = Staff::query()
            ->with(['user', 'formClasses.level'])
            ->withCount('classSubjects')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('designation', 'like', "%{$search}%")
                ->orWhere('staff_no', 'like', "%{$search}%")))
            ->orderBy('last_name')
            ->paginate(30)
            ->withQueryString();

        return view('school.staff.index', [
            'staff' => $staff,
            'search' => $search,
            'canManage' => $request->user()->can('staff.manage'),
        ]);
    }

    public function show(Staff $staff): View
    {
        $staff->load([
            'user',
            'formClasses.level',
            'classSubjects' => fn ($query) => $query->with(['subject', 'classArm.level']),
        ]);

        return view('school.staff.show', ['staff' => $staff]);
    }

    public function create(): View
    {
        return view('school.staff.create', [
            'genderOptions' => collect(Gender::cases())->mapWithKeys(fn ($gender) => [$gender->value => $gender->label()])->all(),
            'employmentOptions' => collect(EmploymentType::cases())->mapWithKeys(fn ($type) => [$type->value => $type->label()])->all(),
            'roleOptions' => collect(SchoolRole::cases())
                ->filter(fn (SchoolRole $role) => $role->isStaff())
                ->mapWithKeys(fn (SchoolRole $role) => [$role->value => $role->label()])
                ->all(),
        ]);
    }

    public function store(Request $request, AddStaffMember $addStaffMember): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:20'],
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'email' => ['required_without:phone', 'nullable', 'email', 'max:160'],
            'phone' => ['required_without:email', 'nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (User::normalisePhone((string) $value) === null) {
                    $fail('Enter a valid Nigerian phone number, e.g. 0803 123 4567.');
                }
            }],
            'designation' => ['required', 'string', 'max:80'],
            'department' => ['nullable', 'string', 'max:80'],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'date_employed' => ['nullable', 'date', 'before_or_equal:today'],
            'qualification' => ['nullable', 'string', 'max:120'],
            'role' => ['required', Rule::in(collect(SchoolRole::cases())->filter->isStaff()->map->value->all())],
        ]);

        $role = SchoolRole::from($data['role']);
        unset($data['role']);

        ['staff' => $staff, 'password' => $password] = $addStaffMember->handle($data, $role);

        $redirect = redirect()->route('school.staff.show', $staff);

        if ($password !== null && $staff->email) {
            Password::broker()->sendResetLink(['email' => $staff->email]);

            return $redirect->with('status', "{$staff->fullName()} has been added. We’ve emailed them a link to choose a password.");
        }

        return $redirect
            ->with('status', "{$staff->fullName()} has been added as {$role->label()}.")
            ->with('credentials', $password ? [['who' => $staff->fullName(), 'login' => $staff->phone, 'password' => $password]] : []);
    }
}
