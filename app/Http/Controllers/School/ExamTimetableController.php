<?php

namespace App\Http\Controllers\School;

use App\Actions\Academic\GenerateExamTimetable;
use App\Enums\StaffStatus;
use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\ClassSubject;
use App\Models\ExamPaper;
use App\Models\Staff;
use App\Models\Term;
use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The end-of-term exam timetable: build it paper by paper or draft it in one go,
 * without a class sitting two papers at once or an invigilator in two halls.
 */
class ExamTimetableController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isStaffInCurrentSchool() || $user->is_platform_admin, 403);

        $term = $this->term($request);
        $levels = ClassLevel::query()->whereHas('arms.classSubjects')->orderBy('position')->get();
        $level = $levels->firstWhere('id', $request->integer('level'));
        $papers = $this->papers($term, $level);

        return view('school.exams.index', [
            'term' => $term,
            'terms' => Term::options(),
            'levels' => $levels,
            'level' => $level,
            'days' => $papers->groupBy(fn (ExamPaper $paper) => $paper->starts_at->toDateString()),
            'paperCount' => $papers->count(),
            'canManage' => $user->can('timetable.manage'),
            'subjectsByLevel' => $levels->mapWithKeys(fn (ClassLevel $classLevel) => [$classLevel->id => ClassSubject::query()->with('subject')
                ->whereIn('class_arm_id', $classLevel->arms()->pluck('id'))->get()->pluck('subject')->unique('id')->sortBy('name')
                ->mapWithKeys(fn ($subject) => [$subject->id => $subject->name])->all()]),
            'invigilators' => Staff::query()->where('status', StaffStatus::Active)->orderBy('last_name')->get()->mapWithKeys(fn (Staff $staff) => [$staff->id => $staff->fullName()])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $paper = new ExamPaper(['term_id' => $this->term($request)->id]);
        $this->save($request, $paper, 'paper');

        return back()->with('status', "{$paper->subject->name} for {$paper->classLevel->name} added on {$paper->starts_at->format('D j M, g:ia')}.");
    }

    public function update(Request $request, ExamPaper $examPaper): RedirectResponse
    {
        $this->save($request, $examPaper, 'editPaper');

        return back()->with('status', "{$examPaper->subject->name} for {$examPaper->classLevel->name} saved.");
    }

    public function destroy(ExamPaper $examPaper): RedirectResponse
    {
        $examPaper->delete();

        return back()->with('status', 'Paper removed from the timetable.');
    }

    public function generate(Request $request, GenerateExamTimetable $generate): RedirectResponse
    {
        $data = $request->validateWithBag('generate', [
            'term_id' => ['required', TenantRule::exists('terms')],
            'starts_on' => ['required', 'date'],
            'slots' => ['required', 'array', 'min:1', 'max:4'],
            'slots.*' => ['required', 'date_format:H:i', 'distinct'],
            'levels' => ['nullable', 'array'],
            'levels.*' => ['integer', TenantRule::exists('class_levels')],
        ], ['slots.*.distinct' => 'Each sitting needs a different start time.']);

        $term = Term::query()->findOrFail($data['term_id']);
        $result = $generate->handle($term, Carbon::parse($data['starts_on']), array_values($data['slots']), $data['levels'] ?? null);
        activity('exams')->log("drafted the {$term->name} exam timetable");

        return to_route('school.exams.index', ['term' => $term->id])->with('status', $result['created']
            ? "Drafted {$result['created']} papers, ending {$result['last_day']?->format('l j F')}. Add venues and invigilators, or move any paper."
            : 'Every subject already has a paper.');
    }

    public function clear(Request $request): RedirectResponse
    {
        $term = $this->term($request);
        $deleted = ExamPaper::query()->where('term_id', $term->id)
            ->when($request->filled('level'), fn ($query) => $query->where('class_level_id', $request->integer('level')))
            ->delete();

        return to_route('school.exams.index', ['term' => $term->id])->with('status', "Removed {$deleted} papers.");
    }

    /**
     * The timetable for the whole school or one level, school-branded, to print or pin up.
     */
    public function print(Request $request): View
    {
        $term = $this->term($request);
        $level = ClassLevel::query()->find($request->integer('level'));

        return view('school.exams.print', [
            'term' => $term,
            'level' => $level,
            'days' => $this->papers($term, $level)->groupBy(fn (ExamPaper $paper) => $paper->starts_at->toDateString()),
        ]);
    }

    private function save(Request $request, ExamPaper $paper, string $bag): void
    {
        $data = $request->validateWithBag($bag, [
            'class_level_id' => ['required', TenantRule::exists('class_levels')],
            'subject_id' => ['required', TenantRule::exists('subjects')],
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'between:15,300'],
            'venue' => ['nullable', 'string', 'max:80'],
            'invigilator_id' => ['nullable', TenantRule::exists('staff')],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $startsAt = Carbon::parse($data['date'].' '.$data['time']);
        $endsAt = $startsAt->copy()->addMinutes((int) $data['duration_minutes']);
        $others = ExamPaper::query()->where('term_id', $paper->term_id)->whereKeyNot($paper->id ?? 0)->overlapping($startsAt, $endsAt);

        if ((clone $others)->where('class_level_id', $data['class_level_id'])->exists()) {
            throw ValidationException::withMessages(['time' => 'That class already has a paper at this time.'])->errorBag($bag);
        }

        if (! empty($data['invigilator_id']) && (clone $others)->where('invigilator_id', $data['invigilator_id'])->exists()) {
            throw ValidationException::withMessages(['invigilator_id' => 'That teacher is invigilating another paper then.'])->errorBag($bag);
        }

        $paper->fill([
            'class_level_id' => $data['class_level_id'],
            'subject_id' => $data['subject_id'],
            'starts_at' => $startsAt,
            'duration_minutes' => $data['duration_minutes'],
            'venue' => $data['venue'] ?? null,
            'invigilator_id' => $data['invigilator_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ])->save();
        $paper->load(['subject', 'classLevel']);
    }

    /**
     * @return Collection<int, ExamPaper>
     */
    private function papers(Term $term, ?ClassLevel $level): Collection
    {
        return ExamPaper::query()
            ->with(['classLevel', 'subject', 'invigilator'])
            ->where('term_id', $term->id)
            ->when($level, fn ($query) => $query->where('class_level_id', $level->id))
            ->orderBy('starts_at')
            ->get()
            ->sortBy(fn (ExamPaper $paper) => $paper->starts_at->format('YmdHi').sprintf('%03d', $paper->classLevel->position))
            ->values();
    }

    private function term(Request $request): Term
    {
        return ($request->filled('term') ? Term::query()->find($request->integer('term')) : null)
            ?? ($request->filled('term_id') ? Term::query()->find($request->integer('term_id')) : null)
            ?? Term::current()
            ?? abort(404);
    }
}
