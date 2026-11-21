@php
    use App\Enums\PromotionDecision;
    use App\Models\ReportTrait;
    use App\Support\Results\ResultCalculator;
    $term = $results->term;
    $arm = $results->arm;
    $school = $currentSchool;
    $priorTerms = $sessionTerms->reject(fn ($sessionTerm) => $sessionTerm->is($term));
    // Until every score is in (usually the exam), the card shows results so far: marks out of what has been set, no grades or positions.
    $soFar = ! $row['complete'];
    $session = $soFar ? null : $session;
    $missing = $results->missingComponents();
    $trim = fn (float $value): string => rtrim(rtrim(number_format($value, 1), '0'), '.');
    $cumulativeFor = function (int $classSubjectId) use ($session): ?float {
        $totals = $session['subjects'][$classSubjectId] ?? [];

        return $totals === [] ? null : round(array_sum($totals) / count($totals), 1);
    };
@endphp

<x-layouts.print :title="$student->fullName().($soFar ? ' results so far' : ' report card')" :document="$soFar ? 'Results so far' : 'Report card'" :school="$school">
    <x-slot:meta>
        <div class="mt-1.5 text-lg font-bold">{{ $term->name }}</div>
        <div class="text-sm text-muted">{{ $term->academicSession->name }} session</div>
    </x-slot:meta>

        <section class="grid grid-cols-2 gap-x-8 gap-y-1.5 border-b border-line py-4 text-sm sm:grid-cols-4">
            <div><span class="text-muted">Name</span><div class="font-semibold">{{ $student->registerName() }}</div></div>
            <div><span class="text-muted">Admission no.</span><div class="font-mono font-semibold">{{ $student->admission_no }}</div></div>
            <div><span class="text-muted">Class</span><div class="font-semibold">{{ $arm->displayName() }}</div></div>
            <div><span class="text-muted">Gender</span><div class="font-semibold">{{ $student->gender->label() }}</div></div>
        </section>

        @if ($soFar)
            <div class="mt-4 rounded-xl border border-gold-200 bg-gold-50 px-4 py-3 text-sm text-gold">
                <span class="font-semibold">These are results so far, not the final report.</span>
                {{ $missing->isNotEmpty() ? $missing->pluck('name')->join(', ', ' and ').' scores are not in yet' : 'Some scores are not in yet' }}, so each subject shows the marks earned out of the marks set so far. Grades and positions come with the final report card.
            </div>

            <table class="mt-4 w-full border-collapse text-left text-sm">
                <thead>
                    <tr class="bg-brand-600 text-xs font-semibold text-white uppercase">
                        <th scope="col" class="px-3 py-2">Subject</th>
                        @foreach ($results->components as $component)
                            <th scope="col" class="px-2 py-2 text-center">{{ $component->short_name }} ({{ $component->max_score }})</th>
                        @endforeach
                        <th scope="col" class="px-2 py-2 text-center">So far</th>
                        <th scope="col" class="px-2 py-2 text-center">%</th>
                        <th scope="col" class="px-2 py-2 text-center">Class avg. %</th>
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
                                <td class="px-2 py-1.5 text-center">{{ isset($cell['scores'][$component->id]) ? $trim($cell['scores'][$component->id]) : '—' }}</td>
                            @endforeach
                            <td class="px-2 py-1.5 text-center font-bold whitespace-nowrap">{{ $trim($cell['total']) }} / {{ $cell['max'] }}</td>
                            <td class="px-2 py-1.5 text-center font-semibold">{{ $trim($cell['percent']) }}%</td>
                            <td class="px-2 py-1.5 text-center text-muted">{{ isset($results->statistics[$classSubject->id]) ? $trim($results->statistics[$classSubject->id]['percent']).'%' : '—' }}</td>
                            <td class="px-3 py-1.5 whitespace-nowrap">{{ $cell['complete'] ? $cell['grade'].' · '.$cell['remark'] : 'In progress' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <section class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ([
                    'Average so far' => $row['percent'] !== null ? $trim($row['percent']).'%' : '—',
                    'Subjects with scores' => $row['subject_count'],
                    'Still to come' => $missing->isNotEmpty() ? $missing->pluck('name')->join(', ') : 'Some scores',
                ] as $label => $value)
                    <div class="rounded-xl border border-brand-200 bg-brand-50 px-4 py-3">
                        <div class="text-xs text-brand-700">{{ $label }}</div>
                        <div class="text-lg font-bold">{{ $value }}</div>
                    </div>
                @endforeach
            </section>
        @else
        <table @class(['mt-4 w-full border-collapse text-left', $session ? 'text-[13px]' : 'text-sm'])>
            <thead>
                <tr class="bg-brand-600 text-xs font-semibold text-white uppercase">
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
                    @if ($showPositions)
                        <th scope="col" class="px-2 py-2 text-center">Position</th>
                    @endif
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
                        @if ($showPositions)
                            <td class="px-2 py-1.5 text-center">{{ isset($cell['position']) ? ResultCalculator::ordinal($cell['position']) : '—' }}</td>
                        @endif
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
                'Position' => $showPositions && isset($row['position']) ? ResultCalculator::ordinal($row['position']).' of '.$results->classSize : null,
                'Subjects offered' => $session ? null : $row['subject_count'],
                'Session average' => $session ? ($session['average'] ?? '—') : null,
                'Session position' => $showPositions && $session && isset($session['position']) ? ResultCalculator::ordinal($session['position']).' of '.$results->classSize : null,
            ], fn ($value) => $value !== null) as $label => $value)
                <div class="rounded-xl border border-brand-200 bg-brand-50 px-4 py-3">
                    <div class="text-xs text-brand-700">{{ $label }}</div>
                    <div class="text-lg font-bold">{{ $value }}</div>
                </div>
            @endforeach
        </section>
        @endif

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
            <section class="mt-4 rounded-xl border-2 border-brand-600 px-4 py-3 text-brand-700 text-center text-[15px] font-bold tracking-wide uppercase">
                @if ($report->promotion_decision === PromotionDecision::Graduated)
                    Graduated
                @elseif ($report->promotion_decision->movesUp())
                    {{ $report->promotion_decision->label() }}{{ $nextLevel ? ' to '.$nextLevel->name : '' }}
                @else
                    Advised to repeat {{ $arm->level->name }}
                @endif
            </section>
        @endif

        <x-slot:footer>
            Grading: {{ collect($results->scheme->bands)->map(fn ($band) => $band['grade'].' '.$band['min'].'+')->join(' · ') }}
            @if ($term->next_term_begins_on) · Next term begins {{ $term->next_term_begins_on->format('l j F Y') }}@endif
        </x-slot:footer>
</x-layouts.print>
