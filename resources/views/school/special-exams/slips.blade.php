{{--
    One result slip per candidate who sat, two to an A4 page, to hand out or send home.
--}}
@use('App\Support\Results\ResultCalculator')

@php
    $school = $currentSchool;
    $sat = $results->candidates->filter(fn ($row) => $row['sat'] > 0)->values();
@endphp

<x-layouts.base :title="$exam->title.' result slips'">
    <x-slot:head>
        <style>
            @media print {
                @page { size: A4 portrait; margin: 10mm; }
                .slip-page { break-after: page; margin: 0 !important; box-shadow: none !important; border: 0 !important; }
                .slip-page:last-child { break-after: auto; }
            }
        </style>
    </x-slot:head>

    <x-print.toolbar width="max-w-[210mm]">
        <span class="text-sm text-muted">{{ $sat->count() }} {{ Str::plural('slip', $sat->count()) }} · two to a page</span>
    </x-print.toolbar>

    @if ($sat->isEmpty())
        <div class="mx-auto my-10 max-w-md px-4"><x-card><x-empty-state icon="scroll-text" title="No slips to print" body="Enter marks first. Slips are printed for every candidate with at least one mark." /></x-card></div>
    @endif

    <div class="mx-auto flex max-w-[210mm] flex-col items-center gap-6 py-6 print:block print:py-0">
        @foreach ($sat->chunk(2) as $page)
            <section class="slip-page flex w-[210mm] flex-col gap-[8mm] bg-white p-[10mm] shadow-card print:p-0">
                @foreach ($page as $row)
                    <article class="flex flex-col gap-3 rounded-xl border border-brand-200 p-5 break-inside-avoid">
                        <x-print.letterhead :school="$school" :document="$exam->kind->label()">
                            <div class="mt-1 text-sm font-bold">{{ $exam->title }}</div>
                            <div class="text-xs text-muted">{{ $exam->held_on->format('j F Y') }}</div>
                        </x-print.letterhead>

                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <div class="text-lg font-bold">{{ $row['student']->registerName() }}</div>
                                <div class="text-sm text-muted">{{ $row['class'] }} · {{ $row['student']->admission_no }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold tabular-nums">{{ $row['total'] }} <span class="text-base font-medium text-muted">/ {{ $results->maxTotal }}</span></div>
                                <div class="text-sm text-muted">{{ $row['percent'] }}% · {{ ResultCalculator::ordinal($row['position']) }} of {{ $sat->count() }}</div>
                            </div>
                        </div>

                        <table class="w-full border-collapse text-left text-sm">
                            <thead>
                                <tr class="border-b-2 border-brand-600 text-xs font-semibold text-muted uppercase">
                                    <th scope="col" class="py-1.5 pr-3">Paper</th>
                                    <th scope="col" class="px-3 py-1.5 text-right">Mark</th>
                                    <th scope="col" class="px-3 py-1.5 text-right">Out of</th>
                                    <th scope="col" class="py-1.5 pl-3 text-right">Exam average</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($results->paperResults as $paper)
                                    @php($mark = $row['scores'][$paper['paper']->id] ?? null)
                                    <tr class="border-b border-line">
                                        <th scope="row" class="py-1.5 pr-3 text-left font-medium">{{ $paper['paper']->name }}</th>
                                        <td class="px-3 py-1.5 text-right font-semibold tabular-nums">{{ $mark ?? 'Absent' }}</td>
                                        <td class="px-3 py-1.5 text-right tabular-nums text-muted">{{ $paper['paper']->max_score }}</td>
                                        <td class="py-1.5 pl-3 text-right tabular-nums text-muted">{{ $paper['average'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="flex items-center justify-between gap-3 text-sm">
                            @if ($row['passed'])
                                <x-badge tone="naija">Passed · at or above {{ $exam->pass_mark }}%</x-badge>
                            @else
                                <x-badge tone="danger">Below the {{ $exam->pass_mark }}% pass mark</x-badge>
                            @endif
                            <span class="w-44 border-t border-dashed border-line pt-1 text-center text-xs text-muted">Principal</span>
                        </div>
                    </article>
                @endforeach
            </section>
        @endforeach
    </div>
</x-layouts.base>
