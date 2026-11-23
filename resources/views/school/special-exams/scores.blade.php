<x-layouts.app :title="'Marks · '.$exam->title">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.special-exams.index') }}" class="hover:text-ink">Special exams</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <a href="{{ route('school.special-exams.show', $exam) }}" class="hover:text-ink">{{ $exam->title }}</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">Marks</span>
    </div>

    <x-page-header kicker="Enter marks" :title="$exam->title" description="Type each candidate’s mark for each paper. Leave a box empty if they did not sit that paper, and clear a box to remove a mark.">
        <x-slot:actions>
            @if ($classes->count() > 1)
                <form method="GET">
                    <label for="class" class="sr-only">Class</label>
                    <select id="class" name="class" class="field w-48 bg-white py-2" x-data x-on:change="$el.form.requestSubmit()">
                        <option value="">All classes</option>
                        @foreach ($classes as $option)
                            <option value="{{ $option }}" @selected($class === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
            <x-button variant="secondary" icon="list-ordered" :href="route('school.special-exams.show', $exam)">See results</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($errors->any())
        <div class="rounded-xl border border-danger/30 bg-danger-50 px-4 py-3 text-sm text-danger" role="alert">{{ $errors->first() }} Nothing was saved.</div>
    @endif

    <x-card :padding="false">
        @if ($rows->isEmpty())
            <x-empty-state icon="users" title="No candidates" />
        @else
            <form method="POST" action="{{ route('school.special-exams.scores.update', $exam) }}">
                @csrf
                @method('PUT')
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-[15px]" style="min-width: {{ 320 + $papers->count() * 96 }}px">
                        <thead>
                            <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                                <th scope="col" class="sticky left-0 bg-white px-5 py-3">Candidate</th>
                                @foreach ($papers as $paper)
                                    <th scope="col" class="px-2 py-3 text-center">{{ $paper->name }}<span class="block font-normal normal-case">/ {{ $paper->max_score }}</span></th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr class="border-t border-line">
                                    <th scope="row" class="sticky left-0 bg-white px-5 py-2 text-left">
                                        <span class="block text-sm font-semibold">{{ $row['student']->registerName() }}</span>
                                        <span class="block text-xs font-normal text-muted">{{ $row['class'] }}</span>
                                    </th>
                                    @foreach ($papers as $paper)
                                        @php($name = "scores[{$row['candidate']->id}][{$paper->id}]")
                                        @php($key = "scores.{$row['candidate']->id}.{$paper->id}")
                                        <td class="px-2 py-2 text-center">
                                            <input
                                                type="number"
                                                name="{{ $name }}"
                                                value="{{ old($key, $row['scores'][$paper->id] ?? null) }}"
                                                min="0"
                                                max="{{ $paper->max_score }}"
                                                step="0.5"
                                                inputmode="decimal"
                                                aria-label="{{ $paper->name }} for {{ $row['student']->fullName() }}"
                                                @class(['field w-20 px-2 py-1.5 text-center tabular-nums', 'field-error' => $errors->has($key)])
                                            >
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="sticky bottom-[var(--tabbar-h,0px)] flex flex-wrap items-center justify-between gap-3 border-t border-line bg-white/95 px-5 py-3 backdrop-blur">
                    <span class="text-sm text-muted">{{ $rows->count() }} {{ Str::plural('candidate', $rows->count()) }}{{ $class ? ' in '.$class : '' }}</span>
                    <x-button variant="brand" icon="check">Save marks</x-button>
                </div>
            </form>
        @endif
    </x-card>
</x-layouts.app>
