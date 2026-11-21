@php
    use App\Support\Results\ResultCalculator;
    $arm = $results->arm;
    $term = $results->term;
    $ranked = $results->students->sortBy(fn ($row) => $row['position'] ?? PHP_INT_MAX)->values();
@endphp

<x-layouts.app :title="$arm->displayName().' results'">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.results.index', ['term' => $term->id]) }}" class="hover:text-ink">Results</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">{{ $arm->displayName() }}</span>
    </div>

    <x-page-header :kicker="$term->name.' · '.$term->academicSession->name" :title="$arm->displayName().' broadsheet'" :description="$results->classSize.' students · '.$results->classSubjects->count().' subjects · '.$results->completion().'% of scores entered · '.$results->scheme->name">
        <x-slot:actions>
            <form method="GET">
                <label for="term" class="sr-only">Term</label>
                <select id="term" name="term" class="field w-60 bg-white py-2" x-data x-on:change="$el.form.requestSubmit()">
                    @foreach ($terms as $id => $label)<option value="{{ $id }}" @selected($term->id === $id)>{{ $label }}</option>@endforeach
                </select>
            </form>
            @if ($canRemark)
                <x-button variant="secondary" icon="message-square-text" :href="route('school.remarks.edit', [$arm, 'term' => $term->id])">Remarks</x-button>
            @endif
            @can('results.publish')
                <form method="POST" action="{{ route('school.results.publish', [$arm, 'term' => $term->id]) }}">
                    @csrf
                    @if ($publication)
                        <x-button variant="secondary" icon="eye">Published · withdraw</x-button>
                    @else
                        <x-button variant="brand" icon="check">Publish to parents</x-button>
                    @endif
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-tabs :tabs="['ranking' => 'Positions', 'broadsheet' => 'Broadsheet', 'subjects' => 'Subject summary']" label="Results views">
        <x-tab name="ranking">
            <x-card :padding="false">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] border-collapse text-left text-[15px]">
                        <thead>
                            <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                                <th scope="col" class="w-20 px-5 py-3 font-semibold">Pos.</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Student</th>
                                <th scope="col" class="px-3 py-3 text-right font-semibold">Subjects</th>
                                <th scope="col" class="px-3 py-3 text-right font-semibold">Total</th>
                                <th scope="col" class="px-3 py-3 text-right font-semibold">Average</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold"><span class="sr-only">Report card</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ranked as $row)
                                <tr class="border-t border-line hover:bg-canvas">
                                    <td class="px-5 py-3 font-bold text-brand-600">{{ isset($row['position']) ? ResultCalculator::ordinal($row['position']) : '—' }}</td>
                                    <td class="px-3 py-3 font-medium">{{ $row['student']->registerName() }}</td>
                                    <td class="px-3 py-3 text-right">{{ $row['subject_count'] }}</td>
                                    <td class="px-3 py-3 text-right">{{ $row['total'] }}</td>
                                    <td class="px-3 py-3 text-right font-semibold">{{ $row['average'] ?? '—' }}</td>
                                    <td class="px-5 py-3 text-right">
                                        @if ($row['subject_count'] > 0)
                                            <a data-native href="{{ route('school.results.report', [$row['student'], 'term' => $term->id]) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-ink">Report card <x-lucide-arrow-right class="size-4" aria-hidden="true" /></a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </x-tab>

        <x-tab name="broadsheet">
            <x-card :padding="false">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead>
                            <tr class="text-xs font-semibold text-muted">
                                <th scope="col" class="sticky left-0 z-10 bg-white px-4 py-3 font-semibold">STUDENT</th>
                                @foreach ($results->classSubjects as $classSubject)
                                    <th scope="col" class="px-2 py-3 text-center font-semibold" title="{{ $classSubject->subject->name }}">{{ $classSubject->subject->code }}</th>
                                @endforeach
                                <th scope="col" class="px-3 py-3 text-center font-semibold">AVG</th>
                                <th scope="col" class="px-3 py-3 text-center font-semibold">POS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($results->students as $row)
                                <tr class="border-t border-line hover:bg-canvas">
                                    <th scope="row" class="sticky left-0 z-10 bg-white px-4 py-2 text-left font-medium whitespace-nowrap">{{ $row['student']->registerName() }}</th>
                                    @foreach ($results->classSubjects as $classSubject)
                                        @php($cell = $row['subjects'][$classSubject->id] ?? null)
                                        <td class="px-2 py-2 text-center">
                                            @if ($cell)
                                                <span @class(['block font-semibold', 'text-danger' => ! $results->scheme->isCredit($cell['grade'])]) title="{{ $cell['remark'] }}{{ $cell['complete'] ? '' : ' (incomplete)' }}">{{ $cell['total'] }}</span>
                                                <span class="block text-[11px] text-muted">{{ $cell['grade'] }}{{ $cell['complete'] ? '' : '*' }}</span>
                                            @else
                                                <span class="text-muted">·</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="px-3 py-2 text-center font-bold">{{ $row['average'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-center font-bold text-brand-600">{{ isset($row['position']) ? ResultCalculator::ordinal($row['position']) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="m-0 border-t border-line px-5 py-3 text-sm text-muted">* Not all of CA1, CA2 and the exam have been entered yet. Grades in red are below a credit.</p>
            </x-card>
        </x-tab>

        <x-tab name="subjects">
            <x-card :padding="false">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] border-collapse text-left text-[15px]">
                        <thead>
                            <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                                <th scope="col" class="px-5 py-3 font-semibold">Subject</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Teacher</th>
                                <th scope="col" class="px-3 py-3 text-right font-semibold">Students</th>
                                <th scope="col" class="px-3 py-3 text-right font-semibold">Average</th>
                                <th scope="col" class="px-3 py-3 text-right font-semibold">Highest</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Lowest</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($results->classSubjects as $classSubject)
                                @php($stats = $results->statistics[$classSubject->id] ?? null)
                                <tr class="border-t border-line">
                                    <td class="px-5 py-3 font-medium">{{ $classSubject->subject->name }}</td>
                                    <td class="px-3 py-3 text-sm text-muted">{{ $classSubject->teacher?->fullName() ?? '—' }}</td>
                                    <td class="px-3 py-3 text-right">{{ $stats['count'] ?? 0 }}</td>
                                    <td class="px-3 py-3 text-right font-semibold">{{ $stats['average'] ?? '—' }}</td>
                                    <td class="px-3 py-3 text-right">{{ $stats['highest'] ?? '—' }}</td>
                                    <td class="px-5 py-3 text-right">{{ $stats['lowest'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </x-tab>
    </x-tabs>
</x-layouts.app>
