<?php

namespace App\Http\Controllers\School;

use App\Enums\InvoiceStatus;
use App\Enums\StaffStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassArm;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Staff;
use App\Models\Term;
use App\Support\Attendance\AttendanceReport;
use App\Support\Finance\CashflowPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * School-admin reports hub: enrolment, fees, attendance, staff.
 */
class ReportController extends Controller
{
    public function index(): View
    {
        return view('school.reports.index', [
            'reports' => [
                ['key' => 'enrolment', 'title' => 'Enrolment by class', 'body' => 'How many students are in each arm this session.', 'icon' => 'graduation-cap'],
                ['key' => 'fees', 'title' => 'Fees & debtors', 'body' => 'Collections this term and who still owes.', 'icon' => 'hand-coins'],
                ['key' => 'attendance', 'title' => 'Attendance summary', 'body' => 'Presence rates, chronic absentees and staff.', 'icon' => 'clipboard-check'],
                ['key' => 'staff', 'title' => 'Staff headcount', 'body' => 'Active staff by department and role.', 'icon' => 'briefcase'],
            ],
        ]);
    }

    public function show(Request $request, string $report): View
    {
        return match ($report) {
            'enrolment' => $this->enrolment(),
            'fees' => $this->fees($request),
            'attendance' => $this->attendance($request),
            'staff' => $this->staff(),
            default => abort(404),
        };
    }

    private function enrolment(): View
    {
        $session = AcademicSession::current();
        $arms = ClassArm::query()
            ->with('level')
            ->withCount(['enrolments' => fn ($query) => $query->where('academic_session_id', $session->id ?? 0)])
            ->get()
            ->sortBy(fn (ClassArm $arm) => sprintf('%03d-%s', $arm->level->position, $arm->name))
            ->values();

        return view('school.reports.enrolment', [
            'session' => $session,
            'arms' => $arms,
            'total' => $arms->sum('enrolments_count'),
        ]);
    }

    private function fees(Request $request): View
    {
        $term = $request->filled('term') ? Term::query()->findOrFail($request->integer('term')) : Term::current();
        abort_if($term === null, 404);

        $period = CashflowPeriod::forTerm($term);
        $owing = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::PartPaid])
            ->selectRaw('sum(total - amount_paid) as owed, count(distinct student_id) as debtors')
            ->first();

        return view('school.reports.fees', [
            'term' => $term,
            'terms' => Term::options(),
            'collected' => $period->income(),
            'payments' => Payment::query()->whereBetween('paid_at', [$period->from, $period->to])->whereNotNull('paid_at')->count(),
            'owed' => (int) ($owing?->getAttribute('owed') ?? 0),
            'debtors' => (int) ($owing?->getAttribute('debtors') ?? 0),
            'byMethod' => $period->incomeByMethod(),
        ]);
    }

    private function attendance(Request $request): View
    {
        $terms = Term::query()->with('academicSession')->orderByDesc('starts_on')->get();
        $term = $terms->firstWhere('id', (int) $request->query('term', Term::current()?->id ?? 0)) ?? $terms->first();
        abort_if($term === null, 404);

        $staff = AttendanceReport::staff($term);

        return view('school.reports.attendance', [
            'term' => $term,
            'terms' => $terms,
            'students' => AttendanceReport::schoolStudents($term),
            'classes' => AttendanceReport::byClass($term),
            'absentees' => AttendanceReport::chronicAbsentees($term, limit: 15),
            'staffSummary' => $staff['summary'],
        ]);
    }

    private function staff(): View
    {
        $members = Staff::query()->orderBy('last_name')->orderBy('first_name')->get();
        $byDepartment = $members->groupBy(fn (Staff $member) => $member->department ?: 'Unassigned')
            ->map(fn ($group) => [
                'total' => $group->count(),
                'active' => $group->where('status', StaffStatus::Active)->count(),
            ])
            ->sortKeys();

        return view('school.reports.staff', [
            'members' => $members,
            'active' => $members->where('status', StaffStatus::Active)->count(),
            'byDepartment' => $byDepartment,
        ]);
    }
}
