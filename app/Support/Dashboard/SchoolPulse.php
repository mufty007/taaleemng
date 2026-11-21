<?php

namespace App\Support\Dashboard;

use App\Enums\ApplicantStatus;
use App\Enums\AttendanceStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\AcademicSession;
use App\Models\Applicant;
use App\Models\AttendanceRecord;
use App\Models\ClassArm;
use App\Models\ClassSubject;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ResultPublication;
use App\Models\Score;
use App\Models\Term;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The whole school at a glance for the office: today's registers, fees, results and admissions.
 * Each method is one dashboard panel, so a role only pays for the panels it sees.
 */
final class SchoolPulse
{
    /** @var Collection<int, ClassArm>|null */
    private ?Collection $arms = null;

    public function __construct(
        public readonly ?AcademicSession $session,
        public readonly ?Term $term,
    ) {}

    /**
     * Classes with students this session, in school order, with their student counts.
     *
     * @return Collection<int, ClassArm>
     */
    public function arms(): Collection
    {
        return $this->arms ??= ClassArm::query()
            ->with('level')
            ->withCount(['enrolments' => fn ($query) => $query->where('academic_session_id', $this->session->id ?? 0)])
            ->get()
            ->filter(fn (ClassArm $arm) => $arm->enrolments_count > 0)
            ->sortBy(fn (ClassArm $arm) => sprintf('%03d-%s', $arm->level->position, $arm->name))
            ->values();
    }

    /**
     * Today's registers across the school.
     *
     * @return array{date: Carbon, open: bool, classes: int, taken: int, present: int, absent: int, late: int, rate: ?int, waiting: Collection<int, ClassArm>, byClass: array<int, int>}
     */
    public function attendanceToday(): array
    {
        $date = today();
        $open = $this->term !== null && ! $date->isWeekend() && $date->betweenIncluded($this->term->starts_on, $this->term->ends_on);

        $rows = AttendanceRecord::query()->whereDate('date', $date)
            ->selectRaw('class_arm_id, status, count(*) as total')->groupBy('class_arm_id', 'status')->get();
        $byStatus = fn (AttendanceStatus $status) => (int) $rows->filter(fn (AttendanceRecord $row) => $row->status === $status)->sum('total');
        $byClass = $rows->groupBy('class_arm_id')->map(fn (Collection $group) => (int) $group->sum('total'))->all();
        $marked = (int) $rows->sum('total');

        return [
            'date' => $date,
            'open' => $open,
            'classes' => $this->arms()->count(),
            'taken' => count($byClass),
            'present' => $byStatus(AttendanceStatus::Present),
            'late' => $byStatus(AttendanceStatus::Late),
            'absent' => $byStatus(AttendanceStatus::Absent),
            'rate' => $marked ? (int) round(($byStatus(AttendanceStatus::Present) + $byStatus(AttendanceStatus::Late)) / $marked * 100) : null,
            'waiting' => $open ? $this->arms()->reject(fn (ClassArm $arm) => isset($byClass[$arm->id]))->values() : collect(),
            'byClass' => $byClass,
        ];
    }

    /**
     * The share of students in school on each of the last school days with a register.
     *
     * @return Collection<int, array{date: Carbon, rate: int}>
     */
    public function attendanceTrend(int $days = 10): Collection
    {
        if ($this->term === null) {
            return collect();
        }

        return AttendanceRecord::query()
            ->where('term_id', $this->term->id)
            ->selectRaw('date, status, count(*) as total')
            ->groupBy('date', 'status')
            ->get()
            ->groupBy(fn (AttendanceRecord $row) => Carbon::parse($row->getRawOriginal('date'))->toDateString())
            ->sortKeys()
            ->take(-$days)
            ->map(function (Collection $group, string $date): array {
                $in = $group->filter(fn (AttendanceRecord $row) => $row->status->isPresent())->sum('total');

                return ['date' => Carbon::parse($date), 'rate' => (int) round($in / max(1, $group->sum('total')) * 100)];
            })
            ->values();
    }

    /**
     * Money: this term's bills against what has come in, what is owed overall, and recent collections.
     *
     * @return array{billed: int, collected: int, rate: ?int, outstanding: int, debtors: int, today: int, weekly: list<array{label: string, amount: int}>, methods: array<string, int>}
     */
    public function fees(): array
    {
        $termInvoices = Invoice::query()->where('term_id', $this->term->id ?? 0)->where('status', '!=', InvoiceStatus::Void);
        $billed = (int) (clone $termInvoices)->sum('total');
        $collected = (int) (clone $termInvoices)->sum('amount_paid');
        $open = Invoice::query()->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::PartPaid]);

        $confirmed = Payment::query()->where('status', PaymentStatus::Confirmed);
        $since = today()->startOfWeek(Carbon::MONDAY)->subWeeks(7);
        $recent = (clone $confirmed)->where('paid_at', '>=', $since)->get(['amount', 'paid_at']);

        $weekly = [];

        for ($week = 0; $week < 8; $week++) {
            $start = $since->copy()->addWeeks($week);
            $weekly[] = [
                'label' => $start->format('j M'),
                'amount' => (int) $recent->filter(fn (Payment $payment) => $payment->paid_at?->betweenIncluded($start, $start->copy()->endOfWeek(Carbon::SUNDAY)))->sum('amount'),
            ];
        }

        return [
            'billed' => $billed,
            'collected' => $collected,
            'rate' => $billed > 0 ? (int) round($collected / $billed * 100) : null,
            'outstanding' => (int) ((clone $open)->sum('total') - (clone $open)->sum('amount_paid')),
            'debtors' => (clone $open)->distinct()->count('student_id'),
            'today' => (int) (clone $confirmed)->whereDate('paid_at', today())->sum('amount'),
            'weekly' => $weekly,
            'methods' => $this->term
                ? (clone $confirmed)->whereBetween('paid_at', [$this->term->starts_on, $this->term->ends_on->copy()->endOfDay()])
                    ->selectRaw('method, sum(amount) as total')->groupBy('method')->get()
                    ->mapWithKeys(fn (Payment $payment) => [$payment->method->label() => (int) $payment->getAttribute('total')])
                    ->sortDesc()->all()
                : [],
        ];
    }

    /**
     * @return Collection<int, Payment>
     */
    public function recentPayments(int $limit = 6): Collection
    {
        return Payment::query()->with('student')->where('status', PaymentStatus::Confirmed)->latest('paid_at')->latest('id')->limit($limit)->get();
    }

    /**
     * How far score entry and result publishing have got this term.
     *
     * @return array{subjects: int, scored: int, rate: ?int, classes: int, published: int, unscored: Collection<int, ClassSubject>}
     */
    public function results(): array
    {
        $armIds = $this->arms()->pluck('id');
        $classSubjects = ClassSubject::query()->with(['subject', 'classArm.level', 'teacher'])->whereIn('class_arm_id', $armIds)->get();
        $scoredIds = Score::query()->where('term_id', $this->term->id ?? 0)->distinct()->pluck('class_subject_id')->flip();

        return [
            'subjects' => $classSubjects->count(),
            'scored' => $classSubjects->filter(fn (ClassSubject $classSubject) => $scoredIds->has($classSubject->id))->count(),
            'rate' => $classSubjects->count() ? (int) round($classSubjects->filter(fn (ClassSubject $classSubject) => $scoredIds->has($classSubject->id))->count() / $classSubjects->count() * 100) : null,
            'classes' => $armIds->count(),
            'published' => ResultPublication::query()->where('term_id', $this->term->id ?? 0)->count(),
            'unscored' => $classSubjects->reject(fn (ClassSubject $classSubject) => $scoredIds->has($classSubject->id))->values(),
        ];
    }

    /**
     * Open applications by stage.
     *
     * @return array<string, int>
     */
    public function admissions(): array
    {
        $counts = Applicant::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(['new', 'exam', 'offered'])->mapWithKeys(fn (string $stage) => [
            $stage => (int) collect(ApplicantStatus::inStage($stage))->sum(fn (ApplicantStatus $status) => $counts[$status->value] ?? 0),
        ])->all();
    }
}
