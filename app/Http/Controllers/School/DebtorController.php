<?php

namespace App\Http\Controllers\School;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\ClassArm;
use App\Models\Invoice;
use App\Models\Student;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Who owes the school, how much, and since when. Exports to CSV for the bursar.
 */
class DebtorController extends Controller
{
    public function index(Request $request): View|StreamedResponse
    {
        $armId = $request->integer('class') ?: null;

        $owing = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::PartPaid])
            ->when($armId, fn (Builder $query) => $query->whereHas('student.currentEnrolment', fn (Builder $query) => $query->where('class_arm_id', $armId)))
            ->groupBy('student_id')
            ->select('student_id', DB::raw('sum(total - amount_paid) as owed'), DB::raw('count(*) as invoices'), DB::raw('min(due_on) as oldest_due'))
            ->orderByDesc('owed')
            ->get();

        $students = Student::query()
            ->with(['currentEnrolment.classArm.level', 'guardians' => fn ($query) => $query->wherePivot('is_primary', true)])
            ->whereKey($owing->pluck('student_id'))
            ->get()
            ->keyBy('id');

        $rows = collect();

        foreach ($owing as $row) {
            $student = $students->get($row->student_id);
            $owed = (int) $row->getAttribute('owed');

            if ($student !== null && $owed > 0) {
                $rows->push(['student' => $student, 'owed' => $owed, 'invoices' => (int) $row->getAttribute('invoices'), 'oldest_due' => $row->getAttribute('oldest_due')]);
            }
        }

        if ($request->query('export') === 'csv') {
            return $this->csv($rows);
        }

        return view('school.debtors.index', [
            'rows' => $rows,
            'total' => $rows->sum('owed'),
            'armId' => $armId,
            'arms' => ClassArm::query()->with('level')->get()->sortBy(fn (ClassArm $arm) => sprintf('%03d-%s', $arm->level->position, $arm->name))->mapWithKeys(fn (ClassArm $arm) => [$arm->id => $arm->displayName()]),
        ]);
    }

    /**
     * @param  Collection<int, array{student: Student, owed: int, invoices: int, oldest_due: mixed}>  $rows
     */
    private function csv(Collection $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Student', 'Admission no.', 'Class', 'Parent', 'Phone', 'Unpaid invoices', 'Amount owed (₦)'], escape: '');

            foreach ($rows as $row) {
                $guardian = $row['student']->guardians->first();
                fputcsv($out, [
                    $row['student']->fullName(),
                    $row['student']->admission_no,
                    $row['student']->currentEnrolment?->classArm->displayName() ?? '',
                    $guardian?->fullName() ?? '',
                    $guardian->phone ?? '',
                    $row['invoices'],
                    Money::toNaira($row['owed']),
                ], escape: '');
            }

            fclose($out);
        }, 'debtors-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
