<?php

namespace App\Http\Controllers\School;

use App\Actions\Timetable\AutofillTimetable;
use App\Enums\PeriodKind;
use App\Enums\StaffStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassArm;
use App\Models\ClassSubject;
use App\Models\Period;
use App\Models\Staff;
use App\Models\TimetableEntry;
use App\Support\Tenancy\TenantRule;
use App\Support\Timetable\Timetable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The timetable: the bell schedule, each class's week (built by hand or filled
 * automatically without teacher clashes) and each teacher's week.
 */
class TimetableController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isStaffInCurrentSchool() || $user->is_platform_admin, 403);
        $canManage = $user->can('timetable.manage');

        // Teachers land on their own week.
        if (! $canManage && ! $request->filled('class') && ($staff = $user->staffRecord()) && Timetable::forTeacher($staff)->isNotEmpty()) {
            return to_route('school.timetable.teacher', $staff);
        }

        $arms = $this->arms();
        $arm = $arms->firstWhere('ulid', $request->query('class')) ?? $arms->first();
        $entries = $arm ? Timetable::forClass($arm) : collect();
        $classSubjects = $arm
            ? ClassSubject::query()->with(['subject', 'teacher'])->where('class_arm_id', $arm->id)->get()->sortBy('subject.name')->values()
            : collect();

        return view('school.timetable.index', [
            'arms' => $arms,
            'arm' => $arm,
            'periods' => Timetable::periods(),
            'grid' => Timetable::grid($entries),
            'classSubjects' => $classSubjects,
            'load' => $classSubjects->map(fn (ClassSubject $classSubject) => [
                'classSubject' => $classSubject,
                'planned' => $entries->where('class_subject_id', $classSubject->id)->count(),
                'target' => Timetable::weeklyTarget($classSubject),
            ]),
            'canManage' => $canManage,
        ]);
    }

    public function teacher(Request $request, Staff $staff): View
    {
        $user = $request->user();
        abort_unless($user->can('timetable.manage') || $user->staffRecord()?->is($staff), 403);

        $entries = Timetable::forTeacher($staff);

        return view('school.timetable.teacher', [
            'staff' => $staff,
            'teachers' => $user->can('timetable.manage') ? Staff::query()->where('status', StaffStatus::Active)->whereHas('classSubjects')->orderBy('last_name')->get() : collect(),
            'periods' => Timetable::periods(),
            'grid' => Timetable::grid($entries),
            'lessonCount' => $entries->count(),
        ]);
    }

    /**
     * Put a subject in one slot of a class's week, or clear it (JSON, from the grid).
     */
    public function saveSlot(Request $request, ClassArm $classArm): JsonResponse
    {
        $data = $request->validate([
            'day' => ['required', 'integer', Rule::in(array_keys(Period::DAYS))],
            'period_id' => ['required', TenantRule::exists('periods')],
            'class_subject_id' => ['nullable', Rule::exists('class_subjects', 'id')->where('class_arm_id', $classArm->id)],
        ]);

        $slot = ['class_arm_id' => $classArm->id, 'day' => (int) $data['day'], 'period_id' => (int) $data['period_id']];

        if (empty($data['class_subject_id'])) {
            TimetableEntry::query()->where($slot)->delete();

            return response()->json(['cleared' => true]);
        }

        abort_unless(Period::query()->findOrFail($data['period_id'])->kind->isLesson(), 422, 'Breaks can’t have lessons.');

        $classSubject = ClassSubject::query()->with(['subject', 'teacher'])->findOrFail($data['class_subject_id']);

        if ($clash = Timetable::clash($classSubject, $slot['day'], $slot['period_id'])) {
            throw ValidationException::withMessages([
                'class_subject_id' => "{$classSubject->teacher?->fullName()} already teaches {$clash->classArm->displayName()} then.",
            ]);
        }

        TimetableEntry::query()->updateOrCreate($slot, ['class_subject_id' => $classSubject->id]);

        return response()->json([
            'subject' => $classSubject->subject->name,
            'teacher' => $classSubject->teacher?->fullName(),
        ]);
    }

    public function autofill(ClassArm $classArm, AutofillTimetable $autofill): RedirectResponse
    {
        $result = $autofill->handle($classArm);

        activity('timetable')->log("filled the timetable for {$classArm->displayName()}");

        return to_route('school.timetable.index', ['class' => $classArm->ulid])->with('status', $result['placed']
            ? "Placed {$result['placed']} lessons for {$classArm->displayName()}.".($result['empty'] ? " {$result['empty']} periods are free; fill them by hand if you need to." : '')
            : 'Nothing to place: every period already has a lesson, or the teachers are busy elsewhere.');
    }

    public function clear(ClassArm $classArm): RedirectResponse
    {
        TimetableEntry::query()->where('class_arm_id', $classArm->id)->delete();
        activity('timetable')->log("cleared the timetable for {$classArm->displayName()}");

        return to_route('school.timetable.index', ['class' => $classArm->ulid])->with('status', "{$classArm->displayName()}’s timetable is empty again.");
    }

    public function periods(): View
    {
        return view('school.timetable.periods', [
            'periods' => Timetable::periods(),
            'kinds' => collect(PeriodKind::cases())->mapWithKeys(fn (PeriodKind $kind) => [$kind->value => $kind->label()])->all(),
        ]);
    }

    public function storePeriod(Request $request): RedirectResponse
    {
        $data = $this->validatePeriod($request, 'addPeriod');
        Period::query()->create([...$data, 'position' => (int) Period::query()->max('position') + 1]);
        $this->reorder();

        return back()->with('status', "{$data['name']} added to the bell schedule.");
    }

    public function updatePeriod(Request $request, Period $period): RedirectResponse
    {
        $data = $this->validatePeriod($request, 'editPeriod');

        if ($period->kind->isLesson() && $data['kind'] !== PeriodKind::Lesson->value) {
            $period->entries()->delete();
        }

        $period->update($data);
        $this->reorder();

        return back()->with('status', "{$period->name} saved.");
    }

    public function destroyPeriod(Period $period): RedirectResponse
    {
        $period->delete();
        $this->reorder();

        return back()->with('status', "{$period->name} removed, with its lessons.");
    }

    public function defaultPeriods(): RedirectResponse
    {
        abort_if(Period::query()->exists(), 409);

        foreach (Period::DEFAULTS as $position => [$name, $starts, $ends, $kind]) {
            Period::query()->create(['name' => $name, 'starts_at' => $starts, 'ends_at' => $ends, 'kind' => $kind, 'position' => $position + 1]);
        }

        return back()->with('status', 'A standard school day is set up: assembly, eight lessons and two breaks. Change any times to match your bell.');
    }

    /**
     * A class's or a teacher's week as a printable, school-branded page.
     */
    public function print(Request $request, string $kind, string $ulid): View
    {
        $user = $request->user();
        abort_unless($user->isStaffInCurrentSchool() || $user->is_platform_admin, 403);

        if ($kind === 'teacher') {
            $staff = Staff::query()->where('ulid', $ulid)->firstOrFail();
            abort_unless($user->can('timetable.manage') || $user->staffRecord()?->is($staff), 403);
            $entries = Timetable::forTeacher($staff);
            $title = $staff->fullName();
        } else {
            $arm = ClassArm::query()->with('level')->where('ulid', $ulid)->firstOrFail();
            $entries = Timetable::forClass($arm);
            $title = $arm->displayName();
        }

        return view('school.timetable.print', [
            'kind' => $kind,
            'title' => $title,
            'periods' => Timetable::periods(),
            'grid' => Timetable::grid($entries),
            'session' => AcademicSession::current(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePeriod(Request $request, string $bag): array
    {
        return $request->validateWithBag($bag, [
            'name' => ['required', 'string', 'max:40'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'kind' => ['required', Rule::enum(PeriodKind::class)],
        ], ['ends_at.after' => 'It must end after it starts.']);
    }

    /**
     * Keep periods in time order.
     */
    private function reorder(): void
    {
        DB::transaction(function (): void {
            Period::query()->orderBy('starts_at')->get()->each(fn (Period $period, int $index) => $period->update(['position' => $index + 1]));
        });
    }

    /**
     * Classes with students this session, in school order.
     *
     * @return Collection<int, ClassArm>
     */
    private function arms(): Collection
    {
        $session = AcademicSession::current();

        return ClassArm::query()
            ->with('level')
            ->withCount(['enrolments' => fn ($query) => $query->where('academic_session_id', $session->id ?? 0)])
            ->get()
            ->filter(fn (ClassArm $arm) => $arm->enrolments_count > 0)
            ->sortBy(fn (ClassArm $arm) => sprintf('%03d-%s', $arm->level->position, $arm->name))
            ->values();
    }
}
