<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Term;
use App\Support\Attendance\AttendanceReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Term attendance reports for the office: class rates, chronic absentees, staff.
 */
class AttendanceReportController extends Controller
{
    public function index(Request $request): View
    {
        $terms = Term::query()->with('academicSession')->orderByDesc('starts_on')->get();
        $termId = (int) $request->query('term', Term::current()?->id ?? 0);
        $term = $terms->firstWhere('id', $termId) ?? $terms->first();

        abort_if($term === null, 404, 'No term has been set up yet.');

        $staff = AttendanceReport::staff($term);

        return view('school.attendance.reports', [
            'term' => $term,
            'terms' => $terms,
            'students' => AttendanceReport::schoolStudents($term),
            'classes' => AttendanceReport::byClass($term),
            'absentees' => AttendanceReport::chronicAbsentees($term),
            'staffSummary' => $staff['summary'],
            'staffRows' => $staff['rows'],
        ]);
    }
}
