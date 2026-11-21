<?php

namespace App\Http\Controllers\School;

use App\Actions\Admissions\EnrolApplicant;
use App\Actions\Admissions\SubmitApplication;
use App\Enums\ApplicantStatus;
use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreApplicationRequest;
use App\Models\AcademicSession;
use App\Models\Applicant;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\SchoolSetting;
use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The admissions pipeline: new applications, entrance exams, offers and enrolment.
 */
class AdmissionController extends Controller
{
    public const STAGES = ['new' => 'New', 'exam' => 'Entrance exam', 'offered' => 'Offered', 'enrolled' => 'Enrolled', 'closed' => 'Closed'];

    public function index(Request $request): View
    {
        $stage = array_key_exists((string) $request->query('stage'), self::STAGES) ? (string) $request->query('stage') : 'new';
        $search = trim((string) $request->query('q'));

        $counts = Applicant::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $stageCounts = collect(self::STAGES)->map(fn (string $label, string $key) => collect(ApplicantStatus::inStage($key))->sum(fn (ApplicantStatus $status) => (int) ($counts[$status->value] ?? 0)));

        $applicants = Applicant::query()
            ->with('classLevel')
            ->whereIn('status', ApplicantStatus::inStage($stage))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('application_no', 'like', "%{$search}%")
                ->orWhere('guardian_phone', 'like', '%'.ltrim($search, '0').'%')))
            ->orderBy($stage === 'exam' ? 'exam_at' : 'created_at', $stage === 'exam' ? 'asc' : 'desc')
            ->paginate(25)
            ->withQueryString();

        return view('school.admissions.index', [
            'stage' => $stage,
            'stages' => self::STAGES,
            'stageCounts' => $stageCounts,
            'applicants' => $applicants,
            'search' => $search,
            'session' => AcademicSession::forAdmissions(),
            'isOpen' => (bool) SchoolSetting::valueFor('admissions_open'),
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreApplicationRequest $request, SubmitApplication $submit): RedirectResponse
    {
        $applicant = $submit->handle($request->validated(), 'office');

        return to_route('school.admissions.show', $applicant)->with('status', "Application {$applicant->application_no} recorded for {$applicant->fullName()}.");
    }

    public function show(Applicant $applicant): View
    {
        $applicant->load(['classLevel', 'academicSession', 'student']);

        return view('school.admissions.show', [
            'applicant' => $applicant,
            'arms' => ClassArm::query()->with(['level', 'stream'])->where('class_level_id', $applicant->class_level_id)->orderBy('name')->get(),
            'nextSteps' => $applicant->status->nextSteps(),
        ]);
    }

    /**
     * Move the application along (or just update the exam and notes).
     */
    public function update(Request $request, Applicant $applicant): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_map(fn (ApplicantStatus $status) => $status->value, $applicant->status->nextSteps()))],
            'exam_at' => ['nullable', 'date', Rule::requiredIf($request->input('status') === ApplicantStatus::ExamScheduled->value)],
            'exam_score' => ['nullable', 'numeric', 'between:0,100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['exam_at.required' => 'Choose when the entrance exam is.']);

        $applicant->update(array_filter([
            'status' => $data['status'] ?? null,
            'exam_at' => $data['exam_at'] ?? null,
        ]) + ['exam_score' => $data['exam_score'] ?? $applicant->exam_score, 'notes' => $data['notes'] ?? null]);

        if (isset($data['status'])) {
            activity('admissions')->performedOn($applicant)->log("moved application {$applicant->application_no} to “{$applicant->status->label()}”");
        }

        return back()->with('status', "{$applicant->fullName()}: {$applicant->status->label()}.");
    }

    /**
     * Book an exam for, or move, several applications at once.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'applicants' => ['required', 'array', 'max:200'],
            'applicants.*' => ['string'],
            'action' => ['required', Rule::in([ApplicantStatus::ExamScheduled->value, ApplicantStatus::Offered->value, ApplicantStatus::Rejected->value])],
            'exam_at' => ['nullable', 'date', 'required_if:action,'.ApplicantStatus::ExamScheduled->value],
        ], [
            'applicants.required' => 'Tick at least one application.',
            'exam_at.required_if' => 'Choose when the entrance exam is.',
        ]);

        $target = ApplicantStatus::from($data['action']);
        $moved = 0;
        $skipped = 0;

        DB::transaction(function () use ($data, $target, &$moved, &$skipped): void {
            foreach (Applicant::query()->whereIn('ulid', $data['applicants'])->get() as $applicant) {
                if (! in_array($target, $applicant->status->nextSteps(), true)) {
                    $skipped++;

                    continue;
                }

                $applicant->update(['status' => $target, ...($target === ApplicantStatus::ExamScheduled ? ['exam_at' => $data['exam_at']] : [])]);
                $moved++;
            }
        });

        activity('admissions')->withProperties(['moved' => $moved])->log("moved {$moved} applications to “{$target->label()}”");

        return back()->with('status', "{$moved} ".str('application')->plural($moved)." moved to “{$target->label()}”.".($skipped ? " {$skipped} skipped: they can’t move there from where they are." : ''));
    }

    public function enrol(Request $request, Applicant $applicant, EnrolApplicant $enrolApplicant): RedirectResponse
    {
        $data = $request->validate([
            'class_arm_id' => ['required', TenantRule::exists('class_arms')],
        ]);

        $result = $enrolApplicant->handle($applicant->load(['classLevel', 'academicSession']), ClassArm::query()->findOrFail($data['class_arm_id']));

        return to_route('school.students.show', $result->student)
            ->with('status', "{$result->student->fullName()} has been enrolled as {$result->student->admission_no}.")
            ->with('credentials', array_filter([
                ['who' => 'Student', 'login' => $result->student->admission_no, 'password' => $result->studentPassword],
                $result->guardianPassword ? ['who' => 'Parent', 'login' => $result->guardian->phone, 'password' => $result->guardianPassword] : null,
            ]));
    }

    public function toggleForm(): RedirectResponse
    {
        $open = ! SchoolSetting::valueFor('admissions_open');
        SchoolSetting::store('admissions_open', $open);
        activity('admissions')->log($open ? 'opened the online admission form' : 'closed the online admission form');

        return back()->with('status', $open ? 'The online admission form is open.' : 'The online admission form is closed. Families see a notice instead.');
    }

    /**
     * @return array{levels: Collection<int, string>, genders: array<string, string>, relationships: array<string, string>}
     */
    private function formOptions(): array
    {
        return [
            'levels' => ClassLevel::query()->orderBy('position')->pluck('name', 'id'),
            'genders' => collect(Gender::cases())->mapWithKeys(fn (Gender $gender) => [$gender->value => $gender->label()])->all(),
            'relationships' => collect(GuardianRelationship::cases())->mapWithKeys(fn (GuardianRelationship $relationship) => [$relationship->value => $relationship->label()])->all(),
        ];
    }
}
