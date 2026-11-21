@php
    use App\Enums\PromotionDecision;
    use App\Models\ReportTrait;
    use App\Support\Results\ResultCalculator;
    $term = $results->term;
    $arm = $results->arm;
    $school = $currentSchool;
    $priorTerms = $sessionTerms->reject(fn ($sessionTerm) => $sessionTerm->is($term));
    $cumulativeFor = function (int $classSubjectId) use ($session): ?float {
        $totals = $session['subjects'][$classSubjectId] ?? [];

        return $totals === [] ? null : round(array_sum($totals) / count($totals), 1);
    };
@endphp

<x-layouts.base :title="$student->fullName().' report card'">
    <x-slot:head>
        <style>
            @media print {
                @page { size: A4; margin: 12mm; }
                .no-print { display: none !important; }
                body { background: white !important; }
                .sheet { box-shadow: none !important; border: 0 !important; margin: 0 !important; padding: 0 !important; }
            }
        </style>
    </x-slot:head>

    <div class="no-print sticky top-0 z-10 border-b border-line bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-[860px] items-center justify-between gap-3 px-4 py-3">
            <a href="{{ url()->previous() }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:text-ink"><x-lucide-arrow-left class="size-4" aria-hidden="true" /> Back</a>
            <x-button icon="printer" type="button" onclick="window.print()">Print or save as PDF</x-button>
        </div>
    </div>

    <main class="sheet mx-auto my-6 max-w-[860px] rounded-2xl border border-line bg-white p-8 shadow-card">
        <header class="flex items-start justify-between gap-6 border-b-2 border-ink pb-5">
            <div>
                <h1 class="m-0 text-2xl font-bold tracking-[-0.02em] uppercase">{{ $school->name }}</h1>
                @if ($school->motto)<p class="m-0 text-sm text-muted italic">“{{ $school->motto }}”</p>@endif
                <p class="m-0 mt-1 text-sm text-muted">{{ $school->address }}{{ $school->phone ? ' · '.$school->phone : '' }}</p>
            </div>
            <div class="text-right">
                <div class="text-xs font-semibold tracking-[0.14em] text-brand-600 uppercase">Report card</div>
                <div class="text-lg font-bold">{{ $term->name }}</div>
                <div class="text-sm text-muted">{{ $term->academicSession->name }} session</div>
            </div>
        </header>

        <section class="grid grid-cols-2 gap-x-8 gap-y-1.5 border-b border-line py-4 text-sm sm:grid-cols-4">
            <div><span class="text-muted">Name</span><div class="font-semibold">{{ $student->registerName() }}</div></div>
            <div><span class="text-muted">Admission no.</span><div class="font-mono font-semibold">{{ $student->admission_no }}</div></div>
            <div><span class="text-muted">Class</span><div class="font-semibold">{{ $arm->displayName() }}</div></div>
            <div><span class="text-muted">Gender</span><div class="font-semibold">{{ $student->gender->label() }}</div></div>
        </section>

        <table @class(['mt-4 w-full border-collapse text-left', $session ? 'text-[13px]' : 'text-sm'])>
            <thead>
                <tr class="bg-ink text-xs font-semibold text-white uppercase">
                    <th scope="col" class="px-3 py-2">Subject</th>
                    @foreach ($results->components as $component)
                        <th scope="col" class="px-2 py-2 text-center">{{ $component->short_name }} ({{ $component->max_score }})</th>
                    @endforeach
                    <th scope="col" class="px-2 py-2 text-center">Total (100)</th>
                    @if ($session)
                        @foreach ($priorTerms as $priorTerm)
                            <th scope="col" class="px-2 py-2 text-center">{{ ResultCalculator::ordinal($priorTerm->sequence) }} term</th>
                        @endforeach
                        <th scope="col" class="px-2 py-2 text-center">Cum. avg.</th>
                    @endif
                    <th scope="col" class="px-2 py-2 text-center">Grade</th>
                    <th scope="col" class="px-2 py-2 text-center">Position</th>
                    <th scope="col" class="px-2 py-2 text-center">Class avg.</th>
                    <th scope="col" class="px-3 py-2">Remark</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($results->classSubjects as $classSubject)
                    @continue(! isset($row['subjects'][$classSubject->id]))
                    @php($cell = $row['subjects'][$classSubject->id])
                    <tr class="border-b border-line">
                        <th scope="row" class="px-3 py-1.5 text-left font-medium">{{ $classSubject->subject->name }}</th>
                        @foreach ($results->components as $component)
                            <td class="px-2 py-1.5 text-center">{{ isset($cell['scores'][$component->id]) ? rtrim(rtrim(number_format($cell['scores'][$component->id], 1), '0'), '.') : '—' }}</td>
                        @endforeach
                        <td class="px-2 py-1.5 text-center font-bold">{{ $cell['total'] }}</td>
                        @if ($session)
                            @foreach ($priorTerms as $priorTerm)
                                <td class="px-2 py-1.5 text-center text-muted">{{ $session['subjects'][$classSubject->id][$priorTerm->sequence] ?? '—' }}</td>
                            @endforeach
                            <td class="px-2 py-1.5 text-center font-semibold">{{ $cumulativeFor($classSubject->id) ?? '—' }}</td>
                        @endif
                        <td class="px-2 py-1.5 text-center font-semibold">{{ $cell['grade'] }}</td>
                        <td class="px-2 py-1.5 text-center">{{ isset($cell['position']) ? ResultCalculator::ordinal($cell['position']) : '—' }}</td>
                        <td class="px-2 py-1.5 text-center text-muted">{{ $results->statistics[$classSubject->id]['average'] ?? '—' }}</td>
                        <td class="px-3 py-1.5 whitespace-nowrap">{{ $cell['remark'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <section @class(['mt-5 grid grid-cols-2 gap-3', $session ? 'sm:grid-cols-5' : 'sm:grid-cols-4'])>
            @foreach (array_filter([
                'Total score' => $row['total'],
                'Average' => $row['average'] ?? '—',
                'Position' => isset($row['position']) ? ResultCalculator::ordinal($row['position']).' of '.$results->classSize : '—',
                'Subjects offered' => $session ? null : $row['subject_count'],
                'Session average' => $session ? ($session['average'] ?? '—') : null,
                'Session position' => $session ? (isset($session['position']) ? ResultCalculator::ordinal($session['position']).' of '.$results->classSize : '—') : null,
            ], fn ($value) => $value !== null) as $label => $value)
                <div class="rounded-xl border border-line px-4 py-3">
                    <div class="text-xs text-muted">{{ $label }}</div>
                    <div class="text-lg font-bold">{{ $value }}</div>
                </div>
            @endforeach
        </section>

        <section class="mt-5 grid grid-cols-1 gap-4 text-sm sm:grid-cols-3">
            <div class="rounded-xl border border-line p-4">
                <div class="text-xs font-semibold tracking-wide text-muted uppercase">Attendance</div>
                <dl class="m-0 mt-2 grid grid-cols-[1fr_auto] gap-y-1">
                    {{-- Typed on the remarks page, or else counted from the class register. --}}
                    @php($opened = $term->school_days ?? ($registerDays ?: null))
                    @php($present = $report?->days_present ?? ($register['days'] > 0 ? $register['present'] + $register['late'] : null))
                    <dt class="text-muted">Times school opened</dt><dd class="m-0 text-right font-semibold">{{ $opened ?? '—' }}</dd>
                    <dt class="text-muted">Times present</dt><dd class="m-0 text-right font-semibold">{{ $present ?? '—' }}</dd>
                    <dt class="text-muted">Times absent</dt><dd class="m-0 text-right font-semibold">{{ $opened !== null && $present !== null ? max(0, $opened - $present) : '—' }}</dd>
                </dl>
            </div>
            @foreach ($traits->groupBy(fn ($trait) => $trait->category->value) as $group)
                <div class="rounded-xl border border-line p-4">
                    <div class="text-xs font-semibold tracking-wide text-muted uppercase">{{ $group->first()->category->label() }}</div>
                    <dl class="m-0 mt-2 grid grid-cols-[1fr_auto] gap-x-3 gap-y-1">
                        @foreach ($group as $trait)
                            @php($rating = $report?->traits[$trait->id] ?? null)
                            <dt class="text-muted">{{ $trait->name }}</dt>
                            <dd class="m-0 text-right font-semibold">{{ $rating ? $rating.' · '.ReportTrait::RATINGS[$rating] : '—' }}</dd>
                        @endforeach
                    </dl>
                </div>
            @endforeach
        </section>

        <section class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div class="flex flex-col rounded-xl border border-line p-4">
                <div class="text-xs font-semibold tracking-wide text-muted uppercase">Form teacher’s comment</div>
                <p class="m-0 mt-1.5 min-h-12 flex-1 text-[15px] leading-relaxed">{{ $report?->teacher_comment }}</p>
                <div class="mt-4 flex items-end justify-between gap-3 border-t border-dashed border-line pt-1 text-xs text-muted">
                    <span>{{ $arm->formTeacher?->fullName() ?? 'Form teacher' }}</span><span>Signature</span>
                </div>
            </div>
            <div class="flex flex-col rounded-xl border border-line p-4">
                <div class="text-xs font-semibold tracking-wide text-muted uppercase">Principal’s comment</div>
                <p class="m-0 mt-1.5 min-h-12 flex-1 text-[15px] leading-relaxed">{{ $report?->principal_comment }}</p>
                <div class="mt-4 flex items-end justify-between gap-3 border-t border-dashed border-line pt-1 text-xs text-muted">
                    <span>Principal / Head teacher</span><span>Signature &amp; stamp</span>
                </div>
            </div>
        </section>

        @if ($session && $report?->promotion_decision)
            <section class="mt-4 rounded-xl border-2 border-ink px-4 py-3 text-center text-[15px] font-bold tracking-wide uppercase">
                @if ($report->promotion_decision === PromotionDecision::Graduated)
                    Graduated
                @elseif ($report->promotion_decision->movesUp())
                    {{ $report->promotion_decision->label() }}{{ $nextLevel ? ' to '.$nextLevel->name : '' }}
                @else
                    Advised to repeat {{ $arm->level->name }}
                @endif
            </section>
        @endif

        <footer class="mt-5 flex flex-wrap justify-between gap-2 border-t border-line pt-3 text-xs text-muted">
            <span>Grading: {{ collect($results->scheme->bands)->map(fn ($band) => $band['grade'].' '.$band['min'].'+')->join(' · ') }}</span>
            @if ($term->next_term_begins_on)<span>Next term begins {{ $term->next_term_begins_on->format('l j F Y') }}</span>@endif
        </footer>
    </main>
</x-layouts.base>
