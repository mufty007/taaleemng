<?php

namespace App\Http\Controllers\School;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\StaffAttendanceRecord;
use App\Support\Attendance\ClassRegister;
use App\Support\Attendance\StaffRegister;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The daily staff register, taken by the office (attendance.view).
 */
class StaffAttendanceController extends Controller
{
    public function register(Request $request): View
    {
        $date = ClassRegister::schoolDay((string) $request->query('date'));
        $register = StaffRegister::for($date);

        $monday = $register->date->copy()->startOfWeek(Carbon::MONDAY);
        $marked = StaffAttendanceRecord::query()
            ->whereBetween('date', [$monday->toDateString(), $monday->copy()->addDays(4)->toDateString()])
            ->distinct()
            ->pluck('date')
            ->map(fn ($day) => Carbon::parse($day)->toDateString())
            ->flip();

        return view('school.attendance.staff', [
            'register' => $register,
            'date' => $register->date,
            'term' => $register->term,
            'week' => collect(range(0, 4))->map(fn (int $offset) => $monday->copy()->addDays($offset)),
            'marked' => $marked,
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $user = $request->user();
        $date = Carbon::parse((string) $request->input('date'))->startOfDay();
        $term = ClassRegister::termFor($date);

        if ($term === null || $date->isWeekend() || $date->isFuture()) {
            throw ValidationException::withMessages(['date' => 'The staff register can only be taken on a school day within a term, up to today.']);
        }

        if ($term->isClosed()) {
            throw ValidationException::withMessages(['date' => "{$term->name} is closed. Ask an admin to reopen it to change the register."]);
        }

        $staffIds = StaffRegister::staffIds();

        $data = $request->validate([
            'date' => ['required', 'date'],
            'records' => ['required', 'array', 'max:500'],
            'records.*.staff' => ['required', 'integer', Rule::in($staffIds)],
            'records.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'records.*.note' => ['nullable', 'string', 'max:200'],
        ]);

        DB::transaction(function () use ($data, $date, $term, $user): void {
            foreach ($data['records'] as $row) {
                StaffAttendanceRecord::query()->updateOrCreate(
                    ['staff_id' => $row['staff'], 'date' => $date->toDateString()],
                    ['term_id' => $term->id, 'status' => $row['status'], 'note' => $row['note'] ?? null, 'recorded_by' => $user->id],
                );
            }
        });

        return response()->json(['saved' => count($data['records'])]);
    }
}
