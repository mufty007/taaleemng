<?php

namespace App\Http\Controllers\School;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\AttendanceRecord;
use App\Models\ClassArm;
use App\Models\Enrolment;
use App\Models\Term;
use App\Models\User;
use App\Support\Attendance\AttendanceSummary;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The daily class register, taken by the form teacher, and the whole-school view for the office.
 */
class AttendanceController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $date = $this->date($request);

        if (! $user->can('attendance.view')) {
            $formClass = ClassArm::query()->where('form_teacher_id', $user->staffRecord()->id ?? 0)->first();

            return $formClass
                ? to_route('school.attendance.register', [$formClass, 'date' => $date->toDateString()])
                : view('school.attendance.not-form-teacher');
        }

        $session = AcademicSession::current();
        $arms = ClassArm::query()
            ->with(['level', 'formTeacher'])
            ->withCount(['enrolments' => fn ($query) => $query->where('academic_session_id', $session->id ?? 0)])
            ->get()
            ->filter(fn (ClassArm $arm) => $arm->enrolments_count > 0)
            ->sortBy(fn (ClassArm $arm) => sprintf('%03d-%s', $arm->level->position, $arm->name))
            ->values();

        $counts = AttendanceRecord::query()
            ->whereDate('date', $date)
            ->selectRaw('class_arm_id, status, count(*) as total')
            ->groupBy('class_arm_id', 'status')
            ->get()
            ->groupBy('class_arm_id')
            ->map(fn (Collection $rows) => $rows->mapWithKeys(fn (AttendanceRecord $row) => [$row->status->value => (int) $row->getAttribute('total')]));

        return view('school.attendance.index', [
            'date' => $date,
            'term' => $this->termFor($date),
            'arms' => $arms,
            'counts' => $counts,
        ]);
    }

    public function register(Request $request, ClassArm $classArm): View
    {
        $this->authorizeRegister($request->user(), $classArm);
        $date = $this->date($request);
        $term = $this->termFor($date);
        $classArm->load(['level', 'formTeacher']);

        $enrolments = $this->enrolments($classArm, $term);
        $records = AttendanceRecord::query()->where('class_arm_id', $classArm->id)->whereDate('date', $date)->get()->keyBy('enrolment_id');
        $summary = $term ? AttendanceSummary::forTerm($term, $enrolments->pluck('id')->all()) : [];

        $monday = $date->copy()->startOfWeek(Carbon::MONDAY);
        $marked = AttendanceRecord::query()
            ->where('class_arm_id', $classArm->id)
            ->whereBetween('date', [$monday->toDateString(), $monday->copy()->addDays(4)->toDateString()])
            ->distinct()
            ->pluck('date')
            ->map(fn ($day) => Carbon::parse($day)->toDateString())
            ->flip();

        return view('school.attendance.register', [
            'arm' => $classArm,
            'date' => $date,
            'term' => $term,
            'week' => collect(range(0, 4))->map(fn (int $offset) => $monday->copy()->addDays($offset)),
            'marked' => $marked,
            'statuses' => AttendanceStatus::cases(),
            'rows' => $enrolments->map(fn (Enrolment $enrolment) => [
                'enrolment' => $enrolment->id,
                'name' => $enrolment->student->registerName(),
                'admission_no' => $enrolment->student->admission_no,
                'status' => $records->get($enrolment->id)?->status->value,
                'note' => $records->get($enrolment->id)->note ?? '',
                'rate' => $summary[$enrolment->id]['rate'] ?? null,
            ])->values(),
            'locked' => $term === null || $term->isClosed() || $date->isFuture() || $date->isWeekend(),
        ]);
    }

    /**
     * Save the register for one day (JSON, from the register page).
     */
    public function save(Request $request, ClassArm $classArm): JsonResponse
    {
        $user = $request->user();
        $this->authorizeRegister($user, $classArm);

        $date = Carbon::parse((string) $request->input('date'))->startOfDay();
        $term = $this->termFor($date);

        if ($term === null || $date->isWeekend() || $date->isFuture()) {
            throw ValidationException::withMessages(['date' => 'The register can only be taken on a school day within a term, up to today.']);
        }

        if ($term->isClosed()) {
            throw ValidationException::withMessages(['date' => "{$term->name} is closed. Ask an admin to reopen it to change the register."]);
        }

        $enrolmentIds = $this->enrolments($classArm, $term)->pluck('id')->all();

        $data = $request->validate([
            'date' => ['required', 'date'],
            'records' => ['required', 'array', 'max:200'],
            'records.*.enrolment' => ['required', 'integer', Rule::in($enrolmentIds)],
            'records.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'records.*.note' => ['nullable', 'string', 'max:200'],
        ]);

        DB::transaction(function () use ($data, $date, $term, $classArm, $user): void {
            foreach ($data['records'] as $row) {
                AttendanceRecord::query()->updateOrCreate(
                    ['enrolment_id' => $row['enrolment'], 'date' => $date->toDateString()],
                    ['term_id' => $term->id, 'class_arm_id' => $classArm->id, 'status' => $row['status'], 'note' => $row['note'] ?? null, 'recorded_by' => $user->id],
                );
            }
        });

        return response()->json(['saved' => count($data['records'])]);
    }

    public static function canTake(User $user, ClassArm $classArm): bool
    {
        return $user->can('attendance.view') && $user->can('attendance.take')
            || ($classArm->form_teacher_id !== null && $classArm->form_teacher_id === $user->staffRecord()?->id);
    }

    private function authorizeRegister(User $user, ClassArm $classArm): void
    {
        abort_unless(self::canTake($user, $classArm), 403);
    }

    /**
     * @return Collection<int, Enrolment>
     */
    private function enrolments(ClassArm $classArm, ?Term $term): Collection
    {
        $sessionId = $term->academic_session_id ?? AcademicSession::current()->id ?? 0;

        return Enrolment::query()
            ->with('student')
            ->where('class_arm_id', $classArm->id)
            ->where('academic_session_id', $sessionId)
            ->get()
            ->sortBy(fn (Enrolment $enrolment) => $enrolment->student->last_name.' '.$enrolment->student->first_name)
            ->values();
    }

    private function termFor(Carbon $date): ?Term
    {
        return Term::query()->with('academicSession')
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();
    }

    private function date(Request $request): Carbon
    {
        try {
            $date = $request->filled('date') ? Carbon::parse((string) $request->query('date')) : today();
        } catch (\Throwable) {
            $date = today();
        }

        // Weekends open on the Friday before.
        return $date->isWeekend() ? $date->previous(Carbon::FRIDAY) : $date->startOfDay();
    }
}
