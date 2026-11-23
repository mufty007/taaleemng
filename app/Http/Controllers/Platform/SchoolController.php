<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\CreateSchool;
use App\Enums\MembershipStatus;
use App\Enums\Plan;
use App\Enums\SchoolRole;
use App\Enums\SchoolStatus;
use App\Enums\SchoolType;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreSchoolRequest;
use App\Models\ClassArm;
use App\Models\Guardian;
use App\Models\Membership;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Support\Platform\Modules;
use App\Support\Platform\SchoolDirectory;
use App\Support\Schools\SetupChecklist;
use App\Support\Tenancy\CurrentSchool;
use BackedEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

/**
 * Schools in the platform console: the directory, one school's page (overview, people,
 * activity, settings), creating a school, and editing its details or trial.
 */
class SchoolController extends Controller
{
    /** @var array<string, string> */
    public const PEOPLE_FILTERS = ['staff' => 'Staff', 'parents' => 'Parents', 'students' => 'Students', 'all' => 'Everyone'];

    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search')),
            'status' => SchoolStatus::tryFrom((string) $request->query('status')),
            'state' => (string) $request->query('state'),
            'sort' => array_key_exists((string) $request->query('sort'), SchoolDirectory::SORTS) ? (string) $request->query('sort') : 'newest',
        ];

        return view('platform.schools.index', [
            'schools' => SchoolDirectory::filtered($filters)->paginate(25)->withQueryString(),
            'filters' => $filters,
            'statusCounts' => SchoolDirectory::statusCounts(),
            'states' => School::query()->whereNotNull('state')->distinct()->orderBy('state')->pluck('state'),
            'sorts' => SchoolDirectory::SORTS,
            'filtered' => $filters['search'] !== '' || $filters['status'] !== null || $filters['state'] !== '',
        ]);
    }

    public function show(Request $request, School $school, CurrentSchool $currentSchool): View
    {
        $school = SchoolDirectory::query()->whereKey($school->id)->firstOrFail();

        $inSchool = $currentSchool->run($school, fn (): array => [
            'stats' => [
                ['label' => 'Students', 'value' => number_format(Student::query()->where('status', StudentStatus::Active)->count()), 'icon' => 'graduation-cap', 'tone' => 'brand'],
                ['label' => 'Staff', 'value' => number_format((int) $school->staff_count), 'icon' => 'briefcase', 'tone' => 'naija'],
                ['label' => 'Classes', 'value' => number_format(ClassArm::query()->count()), 'icon' => 'layers', 'tone' => 'sky'],
                ['label' => 'Parents', 'value' => number_format(Guardian::query()->count()), 'icon' => 'users', 'tone' => 'gold'],
            ],
            'setupSteps' => SetupChecklist::for($school),
            'currentTerm' => Term::current(),
            'payout' => [
                'connected' => filled(SchoolSetting::valueFor('paystack_subaccount')),
                'bank' => SchoolSetting::valueFor('payout_bank_name'),
                'account' => SchoolSetting::valueFor('payout_account_number'),
            ],
        ]);

        $people = $this->people($request, $school);

        return view('platform.schools.show', [
            'school' => $school,
            ...$inSchool,
            'people' => $people,
            'peopleRoles' => $this->rolesFor($school, $people->getCollection()->pluck('id')),
            'peopleSearch' => trim((string) $request->query('people')),
            'peopleFilter' => $this->peopleFilter($request),
            'peopleFilters' => self::PEOPLE_FILTERS,
            'admins' => $this->admins($school),
            'usage' => [
                'members' => $school->users()->wherePivot('status', MembershipStatus::Active->value)->count(),
                'week' => $school->users()->wherePivot('last_active_at', '>=', now()->subDays(7))->count(),
                'month' => $school->users()->wherePivot('last_active_at', '>=', now()->subDays(30))->count(),
                'daily' => $this->dailyActivity($school),
            ],
            'activity' => Activity::query()->where('school_id', $school->id)->with('causer')->latest()->latest('id')
                ->paginate(25, pageName: 'activity_page')->fragment('activity')->withQueryString(),
            'invoices' => $school->invoices()->get(),
            'plans' => Plan::options(),
            'modules' => Modules::ALL,
            'statuses' => SchoolStatus::cases(),
            'schoolTypes' => SchoolType::cases(),
            'states' => config('nigeria.states'),
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

    /**
     * Correct a school's name, codes or contact details from the console.
     */
    public function update(Request $request, School $school): RedirectResponse
    {
        $data = $request->validateWithBag('details', [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::notIn(CreateSchool::RESERVED_SLUGS), Rule::unique('schools', 'slug')->ignore($school->id)],
            'short_code' => ['required', 'string', 'max:6', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('schools', 'short_code')->ignore($school->id)],
            'school_type' => ['required', Rule::enum(SchoolType::class)],
            'state' => ['nullable', Rule::in(config('nigeria.states'))],
            'lga' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:160'],
            'website' => ['nullable', 'url', 'max:160'],
        ], [
            'slug.regex' => 'Use lowercase letters, numbers and single dashes, e.g. greenfield-academy.',
            'short_code.regex' => 'Use letters and numbers only, e.g. GFA.',
        ]);

        $data['short_code'] = Str::upper($data['short_code']);
        $changed = array_keys(array_filter($data, function (mixed $value, string $key) use ($school): bool {
            $current = $school->getAttribute($key);

            return (string) ($current instanceof BackedEnum ? $current->value : $current) !== (string) $value;
        }, ARRAY_FILTER_USE_BOTH));
        $school->update($data);

        activity('platform')->performedOn($school)
            ->tap(fn ($activity) => $activity->school_id = $school->id)
            ->log('updated the school’s details'.($changed !== [] ? ' ('.implode(', ', $changed).')' : ''));

        return redirect()->to(route('platform.schools.show', $school).'#settings')->with('status', "{$school->name}’s details are saved.");
    }

    /**
     * Give a school on trial more time.
     */
    public function extendTrial(Request $request, School $school): RedirectResponse
    {
        $days = (int) $request->validate(['days' => ['required', 'integer', Rule::in([7, 14, 30])]])['days'];
        abort_unless($school->status === SchoolStatus::Trial, 422, 'Only a school on trial can have its trial extended.');

        $from = $school->trial_ends_at && $school->trial_ends_at->isFuture() ? $school->trial_ends_at : now();
        $school->update(['trial_ends_at' => $from->copy()->addDays($days)]);

        activity('platform')->performedOn($school)
            ->tap(fn ($activity) => $activity->school_id = $school->id)
            ->log("extended the trial by {$days} days, to ".$school->trial_ends_at->format('j M Y'));

        return redirect()->to(route('platform.schools.show', $school).'#settings')
            ->with('status', "{$school->name}’s trial now ends on ".$school->trial_ends_at->format('j F Y').'.');
    }

    private function peopleFilter(Request $request): string
    {
        return array_key_exists((string) $request->query('who'), self::PEOPLE_FILTERS) ? (string) $request->query('who') : 'staff';
    }

    /**
     * Members of the school for the People tab, filtered by role group and search.
     *
     * @return LengthAwarePaginator<int, User&object{membership: Membership}>
     */
    private function people(Request $request, School $school): LengthAwarePaginator
    {
        $search = trim((string) $request->query('people'));
        $names = match ($this->peopleFilter($request)) {
            'staff' => collect(SchoolRole::cases())->filter->isStaff()->map->value->values()->all(),
            'parents' => [SchoolRole::Parent->value],
            'students' => [SchoolRole::Student->value],
            default => null,
        };

        return $school->users()
            ->when($names !== null, fn ($query) => $query->whereIn('users.id', DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.school_id', $school->id)
                ->where('model_has_roles.model_type', User::class)
                ->whereIn('roles.name', $names)
                ->select('model_has_roles.model_id')))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('users.name', 'like', "%{$search}%")
                ->orWhere('users.email', 'like', "%{$search}%")
                ->orWhere('users.phone', 'like', '%'.(User::normalisePhone($search) ?? $search).'%')))
            ->orderBy('users.name')
            ->paginate(20, pageName: 'people_page')
            ->fragment('people')
            ->withQueryString();
    }

    /**
     * Each member's roles in this school.
     *
     * @param  Collection<int, mixed>  $userIds
     * @return Collection<int, Collection<int, SchoolRole>>
     */
    private function rolesFor(School $school, Collection $userIds): Collection
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.school_id', $school->id)
            ->where('model_has_roles.model_type', User::class)
            ->whereIn('model_has_roles.model_id', $userIds)
            ->get(['model_has_roles.model_id', 'roles.name'])
            ->groupBy('model_id')
            ->map(fn (Collection $rows): Collection => $rows->map(fn (object $row): ?SchoolRole => SchoolRole::tryFrom($row->name))->filter()->values());
    }

    /**
     * @return EloquentCollection<int, User>
     */
    private function admins(School $school): EloquentCollection
    {
        return $school->users()
            ->whereIn('users.id', DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.school_id', $school->id)
                ->where('roles.name', SchoolRole::SchoolAdmin->value)
                ->select('model_has_roles.model_id'))
            ->orderBy('users.name')
            ->get();
    }

    /**
     * Activity in the school on each of the last 30 days.
     *
     * @return list<array{label: string, value: int, display: string, current: bool}>
     */
    private function dailyActivity(School $school): array
    {
        $since = today()->subDays(29);
        $counts = DB::table('activity_log')
            ->where('school_id', $school->id)
            ->where('created_at', '>=', $since)
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range(0, 29))->map(function (int $offset) use ($since, $counts): array {
            $day = $since->copy()->addDays($offset);
            $value = (int) ($counts[$day->toDateString()] ?? 0);

            return ['label' => $day->format('j'), 'value' => $value, 'display' => number_format($value).' '.str('action')->plural($value).' on '.$day->format('D j M'), 'current' => $day->isToday()];
        })->all();
    }
}
