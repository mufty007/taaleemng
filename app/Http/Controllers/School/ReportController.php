<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Term;
use App\Support\Reports\AcademicReport;
use App\Support\Reports\AdmissionsReport;
use App\Support\Reports\EnrolmentReport;
use App\Support\Reports\FeesReport;
use App\Support\Reports\FinanceReport;
use App\Support\Reports\InventoryReport;
use App\Support\Reports\ReportCatalogue;
use App\Support\Reports\SchoolReport;
use App\Support\Reports\StaffReport;
use App\Support\Reports\TermAttendanceReport;
use App\Support\Results\ResultCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Management reports: one screen per report, the same figures as a printable,
 * school-branded document, and the detail rows as a CSV for Excel. Each report is
 * open to whoever holds its permission in ReportCatalogue.
 */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        return view('school.reports.index', [
            'groups' => ReportCatalogue::visibleTo($request->user()),
        ]);
    }

    public function show(Request $request, string $report): View|StreamedResponse
    {
        $built = $this->build($request, $report);

        if ($request->query('export') === 'csv') {
            return $built->csv();
        }

        return view('school.reports.show', $this->viewData($report, $built));
    }

    public function print(Request $request, string $report): View
    {
        return view('school.reports.print', $this->viewData($report, $this->build($request, $report)));
    }

    private function build(Request $request, string $report): SchoolReport
    {
        $definition = ReportCatalogue::find($report) ?? abort(404);
        abort_unless($request->user()->can($definition['permission']), 403);

        return match ($report) {
            'enrolment' => new EnrolmentReport($this->session($request)),
            'academic' => new AcademicReport($this->term($request), app(ResultCalculator::class)),
            'attendance' => new TermAttendanceReport($this->term($request)),
            'admissions' => new AdmissionsReport($this->session($request, forAdmissions: true)),
            'fees' => new FeesReport($this->term($request)),
            'finance' => new FinanceReport($this->term($request)),
            'inventory' => new InventoryReport($this->term($request)),
            'staff' => new StaffReport(showPay: $request->user()->can('payroll.manage')),
            default => abort(404),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(string $report, SchoolReport $built): array
    {
        $definition = ReportCatalogue::find($report) ?? abort(404);
        $figures = $built->figures();
        $selected = match ($definition['period']) {
            'term' => $figures['term']->id,
            'session' => $figures['session']->id,
            default => null,
        };

        return [
            'report' => $definition,
            'periodLabel' => $built->periodLabel(),
            'figures' => $figures,
            'periodOptions' => match ($definition['period']) {
                'term' => Term::options(),
                'session' => AcademicSession::query()->orderByDesc('starts_on')->pluck('name', 'id')->all(),
                default => [],
            },
            'selectedPeriod' => $selected,
            // Keeps the print and CSV links on the period being looked at.
            'periodQuery' => $definition['period'] !== null ? [$definition['period'] => $selected] : [],
        ];
    }

    /**
     * The term picked in the filter, or the current term, or the latest one set up.
     */
    private function term(Request $request): Term
    {
        $term = $request->filled('term')
            ? Term::query()->with('academicSession')->find($request->integer('term'))
            : (Term::current() ?? Term::query()->with('academicSession')->orderByDesc('starts_on')->first());

        abort_if($term === null, 404, 'No term has been set up yet.');

        return $term;
    }

    /**
     * The session picked in the filter, or the current one. Admissions default to the
     * session the school is admitting for, which is usually the next one.
     */
    private function session(Request $request, bool $forAdmissions = false): AcademicSession
    {
        $session = match (true) {
            $request->filled('session') => AcademicSession::query()->find($request->integer('session')),
            $forAdmissions => AcademicSession::forAdmissions() ?? AcademicSession::query()->orderByDesc('starts_on')->first(),
            default => AcademicSession::current() ?? AcademicSession::query()->orderByDesc('starts_on')->first(),
        };

        abort_if($session === null, 404, 'No session has been set up yet.');

        return $session;
    }
}
