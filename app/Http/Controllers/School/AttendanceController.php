<?php

namespace App\Http\Controllers\School;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\AttendanceRecord;
use App\Models\ClassArm;
use App\Models\User;
use App\Support\Attendance\ClassRegister;
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
            'term' => ClassRegister::termFor($date),
            'arms' => $arms,
            'counts' => $counts,
        ]);
    }

    public function register(Request $request, ClassArm $classArm): View
    {
        $this->authorizeRegister($request->user(), $classArm);
        $classArm->load(['level', 'formTeacher']);
        $register = ClassRegister::for($classArm, $this->date($request));

        $monday = $register->date->copy()->startOfWeek(Carbon::MONDAY);
        $marked = AttendanceRecord::query()
            ->where('class_arm_id', $classArm->id)
            ->whereBetween('date', [$monday->toDateString(), $monday->copy()->addDays(4)->toDateString()])
            ->distinct()
            ->pluck('date')
            ->map(fn ($day) => Carbon::parse($day)->toDateString())
            ->flip();

        return view('school.attendance.register', [
            'arm' => $classArm,
            'register' => $register,
            'date' => $register->date,
            'term' => $register->term,
            'week' => collect(range(0, 4))->map(fn (int $offset) => $monday->copy()->addDays($offset)),
            'marked' => $marked,
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
        $term = ClassRegister::termFor($date);

        if ($term === null || $date->isWeekend() || $date->isFuture()) {
            throw ValidationException::withMessages(['date' => 'The register can only be taken on a school day within a term, up to today.']);
        }

        if ($term->isClosed()) {
            throw ValidationException::withMessages(['date' => "{$term->name} is closed. Ask an admin to reopen it to change the register."]);
        }

        $enrolmentIds = ClassRegister::enrolments($classArm, $term)->pluck('id')->all();

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

    private function date(Request $request): Carbon
    {
        return ClassRegister::schoolDay((string) $request->query('date'));
    }
}
