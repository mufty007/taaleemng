<?php

namespace App\Http\Controllers\School;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\ClassArm;
use App\Models\FeeReminder;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\User;
use App\Support\Finance\FeeStatement;
use App\Support\Money;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Who owes the school, how much, and since when, with a reminder (email, WhatsApp
 * or a pay link) one tap away. Exports to CSV for the bursar.
 */
class DebtorController extends Controller
{
    public function index(Request $request, CurrentSchool $currentSchool): View|StreamedResponse
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

        $canRemind = $request->user()->can('fees.remind');
        $reminders = $canRemind ? $this->reminders($rows, $currentSchool) : collect();

        return view('school.debtors.index', [
            'rows' => $rows,
            'total' => $rows->sum('owed'),
            'canRemind' => $canRemind,
            'reminders' => $reminders,
            'remindedThisWeek' => $reminders->filter(fn (array $reminder): bool => $reminder['last']?->created_at->gt(now()->subWeek()) ?? false)->count(),
            'withEmail' => $rows->filter(fn (array $row): bool => filled($row['student']->guardians->first()?->email))->count(),
            'canPayOnline' => FeeStatement::canPayOnline(),
            'armId' => $armId,
            'arms' => ClassArm::query()->with('level')->get()->sortBy(fn (ClassArm $arm) => sprintf('%03d-%s', $arm->level->position, $arm->name))->mapWithKeys(fn (ClassArm $arm) => [$arm->id => $arm->displayName()]),
        ]);
    }

    /**
     * Per student: the last reminder, a pay link, and a link that opens WhatsApp
     * on the sender's phone with the message already written. (SMS is coming soon.)
     *
     * @param  Collection<int, array{student: Student, owed: int, invoices: int, oldest_due: mixed}>  $rows
     * @return Collection<int, array{last: ?FeeReminder, link: string, whatsapp: ?string, email: ?string, message: string}>
     */
    private function reminders(Collection $rows, CurrentSchool $currentSchool): Collection
    {
        $school = $currentSchool->getOrFail();
        $online = FeeStatement::canPayOnline();
        $last = FeeReminder::query()
            ->whereIn('student_id', $rows->map(fn (array $row): int => $row['student']->id))
            ->with('sender')
            ->latest()
            ->latest('id')
            ->get()
            ->unique('student_id')
            ->keyBy('student_id');

        return $rows->mapWithKeys(function (array $row) use ($school, $online, $last): array {
            /** @var Guardian|null $guardian */
            $guardian = $row['student']->guardians->first();
            $link = FeeStatement::linkFor($row['student']);
            $message = FeeStatement::messageFor($school, $row['student'], $guardian, $row['owed'], $link, $online);
            $phone = User::normalisePhone($guardian?->phone);

            return [$row['student']->id => [
                'last' => $last->get($row['student']->id),
                'link' => $link,
                'whatsapp' => $phone ? 'https://wa.me/'.ltrim($phone, '+').'?text='.rawurlencode($message) : null,
                'email' => $guardian?->email,
                'message' => $message,
            ]];
        });
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
