<?php

namespace App\Http\Controllers\School;

use App\Actions\Fees\AllocatePayments;
use App\Actions\People\EnrolStudent;
use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\PaymentMethod;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreStudentRequest;
use App\Models\AcademicSession;
use App\Models\ClassArm;
use App\Models\Score;
use App\Models\Student;
use App\Models\Term;
use App\Support\TemporaryPassword;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Student::class);

        $user = $request->user();
        $canSeeAll = $user->can('students.view-all');
        $visibleArmIds = $canSeeAll ? null : ($user->staffRecord()?->classArmIds() ?? []);

        $search = trim((string) $request->query('search'));
        $classUlid = (string) $request->query('class');
        $selectedArm = $classUlid !== '' ? ClassArm::query()->where('ulid', $classUlid)->first() : null;

        $students = Student::query()
            ->with(['currentEnrolment.classArm.level', 'guardians' => fn ($query) => $query->wherePivot('is_primary', true)])
            ->when($visibleArmIds !== null, fn (Builder $query) => $query->inClassArms($visibleArmIds))
            ->when($selectedArm, fn (Builder $query) => $query->inClassArms([$selectedArm->id]))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('middle_name', 'like', "%{$search}%")
                ->orWhere('admission_no', 'like', "%{$search}%")))
            ->where('status', $request->query('status', StudentStatus::Active->value))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString();

        return view('school.students.index', [
            'students' => $students,
            'search' => $search,
            'selectedArm' => $selectedArm,
            'classOptions' => $this->classOptions($visibleArmIds, byUlid: true),
            'statusOptions' => collect(StudentStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all(),
            'status' => $request->query('status', StudentStatus::Active->value),
            'canSeeAll' => $canSeeAll,
        ]);
    }

    public function show(Student $student): View
    {
        Gate::authorize('view', $student);

        $student->load([
            'guardians.user',
            'user',
            'enrolments' => fn ($query) => $query->with(['academicSession', 'classArm.level'])->latest('id'),
            'currentEnrolment.classArm' => fn ($query) => $query->with([
                'level', 'stream', 'formTeacher',
                'classSubjects' => fn ($query) => $query->with(['subject', 'teacher']),
            ]),
        ]);

        // Terms in which this student has any scores, newest first, for the Results tab.
        $resultTerms = Term::query()
            ->with('academicSession')
            ->whereIn('id', Score::query()->whereIn('enrolment_id', $student->enrolments->pluck('id'))->distinct()->pluck('term_id'))
            ->orderByDesc('starts_on')
            ->get();

        $seesFees = request()->user()->can('finance.view') || request()->user()->can('payments.record');

        return view('school.students.show', [
            'student' => $student,
            'resultTerms' => $resultTerms,
            'fees' => $seesFees ? [
                'invoices' => $student->invoices()->with('term.academicSession')->latest('issued_at')->get(),
                'payments' => $student->payments()->with('recorder')->latest('paid_at')->latest('id')->limit(25)->get(),
                'balance' => AllocatePayments::balance($student),
                'methods' => collect(PaymentMethod::offline())->mapWithKeys(fn (PaymentMethod $method) => [$method->value => $method->label()])->all(),
            ] : null,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Student::class);

        return view('school.students.create', $this->formOptions() + [
            'hasCurrentSession' => AcademicSession::current() !== null,
        ]);
    }

    public function store(StoreStudentRequest $request, EnrolStudent $enrolStudent): RedirectResponse
    {
        $data = $request->validated();

        $result = $enrolStudent->handle(
            collect($data)->only(array_keys(StoreStudentRequest::studentRules()))->all(),
            ClassArm::query()->findOrFail($data['class_arm_id']),
            [
                'title' => $data['guardian_title'] ?? null,
                'first_name' => $data['guardian_first_name'],
                'last_name' => $data['guardian_last_name'],
                'relationship' => $data['guardian_relationship'],
                'phone' => $data['guardian_phone'],
                'email' => $data['guardian_email'] ?? null,
                'occupation' => $data['guardian_occupation'] ?? null,
            ],
        );

        return redirect()
            ->route('school.students.show', $result->student)
            ->with('status', "{$result->student->fullName()} has been enrolled as {$result->student->admission_no}.")
            ->with('credentials', array_filter([
                ['who' => 'Student', 'login' => $result->student->admission_no, 'password' => $result->studentPassword],
                $result->guardianPassword ? ['who' => 'Parent', 'login' => $result->guardian->phone, 'password' => $result->guardianPassword] : null,
            ]));
    }

    public function edit(Student $student): View
    {
        Gate::authorize('update', $student);

        return view('school.students.edit', $this->formOptions() + ['student' => $student]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        Gate::authorize('update', $student);

        $data = $request->validate([
            ...StoreStudentRequest::studentRules(),
            'status' => ['required', Rule::enum(StudentStatus::class)],
        ]);

        $student->update($data);
        activity('people')->performedOn($student)->log("updated {$student->fullName()}’s record");

        return redirect()->route('school.students.show', $student)->with('status', 'Student record saved.');
    }

    public function resetPassword(Student $student): RedirectResponse
    {
        Gate::authorize('update', $student);
        abort_if($student->user === null, 404);

        $password = TemporaryPassword::generate();
        $student->user->forceFill(['password' => $password])->save();
        activity('people')->performedOn($student)->log("reset {$student->fullName()}’s login password");

        return back()
            ->with('status', 'A new password has been set. Give it to the student.')
            ->with('credentials', [['who' => 'Student', 'login' => $student->admission_no, 'password' => $password]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'classOptions' => $this->classOptions(null),
            'genderOptions' => collect(Gender::cases())->mapWithKeys(fn ($gender) => [$gender->value => $gender->label()])->all(),
            'relationshipOptions' => collect(GuardianRelationship::cases())->mapWithKeys(fn ($relationship) => [$relationship->value => $relationship->label()])->all(),
            'states' => array_combine(config('nigeria.states'), config('nigeria.states')),
            'religions' => ['Islam' => 'Islam', 'Christianity' => 'Christianity', 'Other' => 'Other'],
            'bloodGroups' => array_combine($groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], $groups),
            'genotypes' => array_combine($genotypes = ['AA', 'AS', 'AC', 'SS', 'SC'], $genotypes),
        ];
    }

    /**
     * Class arms as "JSS 2A" options, keyed by id (forms) or ulid (filters).
     *
     * @param  list<int>|null  $onlyArmIds
     * @return array<string, string>
     */
    private function classOptions(?array $onlyArmIds, bool $byUlid = false): array
    {
        return ClassArm::query()
            ->with(['level', 'stream'])
            ->when($onlyArmIds !== null, fn (Builder $query) => $query->whereIn('id', $onlyArmIds))
            ->get()
            ->sortBy(fn (ClassArm $arm) => sprintf('%03d-%s', $arm->level->position, $arm->name))
            ->mapWithKeys(fn (ClassArm $arm) => [($byUlid ? $arm->ulid : $arm->id) => $arm->displayName().($arm->stream ? ' · '.$arm->stream->name : '')])
            ->all();
    }
}
