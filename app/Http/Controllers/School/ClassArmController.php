<?php

namespace App\Http\Controllers\School;

use App\Actions\Academic\AddClassArm;
use App\Actions\Academic\ApplyNigerianDefaults;
use App\Enums\StaffStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\Staff;
use App\Models\Stream;
use App\Models\Subject;
use App\Support\Classwork\ClassworkAccess;
use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassArmController extends Controller
{
    public function index(Request $request): View
    {
        $session = AcademicSession::current();

        $levels = ClassLevel::query()
            ->with(['arms' => fn ($query) => $query
                ->with(['level', 'stream', 'formTeacher'])
                ->withCount([
                    'classSubjects',
                    'enrolments as students_count' => fn (Builder $query) => $query->where('academic_session_id', $session?->id),
                ])])
            ->orderBy('position')
            ->get()
            ->groupBy(fn (ClassLevel $level) => $level->section->label());

        $myArmIds = $request->user()->staffRecord()?->classArmIds() ?? [];

        // Teachers only see the classes they teach or are form teacher of.
        if (! $request->user()->can('students.view-all')) {
            $levels = $levels
                ->map(fn ($sectionLevels) => $sectionLevels
                    ->each(fn (ClassLevel $level) => $level->setRelation('arms', $level->arms->whereIn('id', $myArmIds)->values()))
                    ->filter(fn (ClassLevel $level) => $level->arms->isNotEmpty()))
                ->filter(fn ($sectionLevels) => $sectionLevels->isNotEmpty());
        }

        return view('school.classes.index', [
            'levelsBySection' => $levels,
            'session' => $session,
            'myArmIds' => $myArmIds,
            'canManage' => $request->user()->can('academics.manage'),
            'canSeeAll' => $request->user()->can('students.view-all'),
            'levelOptions' => ClassLevel::query()->orderBy('position')->pluck('name', 'id')->all(),
            'streamOptions' => Stream::query()->orderBy('name')->pluck('name', 'id')->all(),
            'teacherOptions' => $this->teacherOptions(),
        ]);
    }

    public function show(Request $request, ClassArm $classArm): View
    {
        $user = $request->user();
        abort_unless(
            $user->can('students.view-all') || in_array($classArm->id, $user->staffRecord()?->classArmIds() ?? [], true),
            404,
        );

        $session = AcademicSession::current();
        $classArm->load(['level', 'stream', 'formTeacher', 'classSubjects' => fn ($query) => $query->with(['subject', 'teacher'])]);

        $enrolments = $classArm->enrolments()
            ->where('academic_session_id', $session?->id)
            ->with(['student' => fn ($query) => $query->with('guardians')])
            ->get()
            ->sortBy(fn ($enrolment) => $enrolment->student->last_name.' '.$enrolment->student->first_name)
            ->values();

        return view('school.classes.show', [
            'classArm' => $classArm,
            'enrolments' => $enrolments,
            'session' => $session,
            'canManage' => $user->can('academics.manage'),
            'canRegister' => AttendanceController::canTake($user, $classArm),
            'myClassSubjectId' => $classArm->classSubjects->first(fn ($classSubject) => ClassworkAccess::canManage($user, $classSubject))?->id,
            'teacherOptions' => $this->teacherOptions(),
            'subjectOptions' => Subject::query()
                ->whereNotIn('id', $classArm->classSubjects->pluck('subject_id'))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
        ]);
    }

    public function store(Request $request, AddClassArm $addClassArm): RedirectResponse
    {
        $data = $request->validateWithBag('addArm', [
            'class_level_id' => ['required', TenantRule::exists('class_levels')],
            'name' => ['required', 'string', 'max:20', Rule::unique('class_arms')->where('class_level_id', $request->input('class_level_id'))],
            'stream_id' => ['nullable', TenantRule::exists('streams')],
            'form_teacher_id' => ['nullable', TenantRule::exists('staff')],
        ], [
            'name.unique' => 'That class already has an arm with this name.',
        ]);

        $arm = $addClassArm->handle(
            ClassLevel::query()->findOrFail($data['class_level_id']),
            strtoupper(trim($data['name'])),
            isset($data['stream_id']) ? Stream::query()->find($data['stream_id']) : null,
            $data['form_teacher_id'] ?? null,
        );

        return back()->with('status', "{$arm->load('level')->displayName()} has been added with its default subjects.");
    }

    public function update(Request $request, ClassArm $classArm): RedirectResponse
    {
        $data = $request->validateWithBag('editArm', [
            'name' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('class_arms')->where('class_level_id', $classArm->class_level_id)->ignore($classArm->id)],
            'stream_id' => ['sometimes', 'nullable', TenantRule::exists('streams')],
            'capacity' => ['sometimes', 'nullable', 'integer', 'between:1,200'],
            'form_teacher_id' => ['nullable', TenantRule::exists('staff')],
        ], ['name.unique' => 'That class already has an arm with this name.']);

        if (isset($data['name'])) {
            $data['name'] = strtoupper(trim($data['name']));
        }

        $classArm->update($data);
        $classArm->load('level');

        activity('academics')->performedOn($classArm)->log("updated {$classArm->displayName()}");

        return back()->with('status', "{$classArm->displayName()} saved.");
    }

    public function destroy(ClassArm $classArm): RedirectResponse
    {
        $classArm->load('level');

        if ($classArm->enrolments()->exists()) {
            return back()->with('status', "{$classArm->displayName()} has students on record, so it can’t be deleted. Move them first.");
        }

        $name = $classArm->displayName();
        $classArm->delete();
        activity('academics')->log("deleted the class {$name}");

        return back()->with('status', "{$name} has been deleted.");
    }

    public function applyDefaults(ApplyNigerianDefaults $applyDefaults): RedirectResponse
    {
        $applyDefaults->handle();

        return back()->with('status', 'Classes and subjects are set up. Assign form teachers and subject teachers next.');
    }

    /**
     * @return array<int, string>
     */
    private function teacherOptions(): array
    {
        return Staff::query()
            ->where('status', StaffStatus::Active)
            ->orderBy('last_name')
            ->get()
            ->mapWithKeys(fn (Staff $staff) => [$staff->id => $staff->fullName().' · '.$staff->designation])
            ->all();
    }
}
