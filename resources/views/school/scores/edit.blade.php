<x-layouts.app :title="$classSubject->subject->name.' · '.$classSubject->classArm->displayName()">
    @php
        $componentData = $components->map(fn ($component) => ['id' => $component->id, 'short' => $component->short_name, 'max' => $component->max_score])->values();
    @endphp

    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.scores.index') }}" class="hover:text-ink">Score entry</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">{{ $classSubject->classArm->displayName() }} · {{ $classSubject->subject->name }}</span>
    </div>

    <div
        x-data="scorebook({ rows: @js($rows), components: @js($componentData), bands: @js($gradeBands), url: @js(route('school.scores.update', $classSubject)), locked: @js($term->isClosed()) })"
        class="flex flex-col gap-4"
    >
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="kicker">{{ $term->name }} · {{ $term->academicSession->name }}</div>
                <h1 class="m-0 mt-1 text-[28px] leading-tight font-bold tracking-[-0.03em]">{{ $classSubject->subject->name }} · {{ $classSubject->classArm->displayName() }}</h1>
                <p class="m-0 mt-1 text-[15px] text-muted">{{ $classSubject->teacher?->fullName() ?? 'No teacher assigned' }} · {{ $components->map(fn ($c) => $c->short_name.' /'.$c->max_score)->join(' + ') }}</p>
            </div>
            <div class="flex items-center gap-2 rounded-xl border border-line bg-white px-3.5 py-2 text-sm" aria-live="polite">
                <template x-if="saving"><span class="flex items-center gap-2 text-muted"><span class="size-2 animate-pulse rounded-full bg-gold"></span>Saving…</span></template>
                <template x-if="! saving && pending"><span class="flex items-center gap-2 text-gold"><span class="size-2 rounded-full bg-gold"></span><span x-text="pending + ' unsaved'"></span></span></template>
                <template x-if="! saving && ! pending && savedAt"><span class="flex items-center gap-2 text-naija"><span class="size-2 rounded-full bg-naija"></span><span x-text="'All saved · ' + savedAt"></span></span></template>
                <template x-if="! saving && ! pending && ! savedAt"><span class="text-muted"><span x-text="entered"></span> scores entered</span></template>
            </div>
        </div>

        @if ($term->isClosed())
            <div class="rounded-2xl border border-gold-200 bg-gold-50 px-5 py-3 text-[15px] text-gold"><b>{{ $term->name }} is closed.</b> Scores can be viewed but not changed.</div>
        @endif

        <div x-show="error" x-cloak class="rounded-2xl bg-danger-50 px-5 py-3 text-[15px] font-medium text-danger" role="alert" x-text="error"></div>

        <x-card :padding="false">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] border-collapse text-left text-[15px]">
                    <thead class="sticky top-0 bg-white">
                        <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                            <th scope="col" class="w-10 px-4 py-3 font-semibold">#</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Student</th>
                            @foreach ($components as $component)
                                <th scope="col" class="w-24 px-2 py-3 text-center font-semibold">{{ $component->short_name }} <span class="font-normal normal-case">/{{ $component->max_score }}</span></th>
                            @endforeach
                            <th scope="col" class="w-20 px-3 py-3 text-center font-semibold">Total</th>
                            <th scope="col" class="w-20 px-4 py-3 text-center font-semibold">Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, rowIndex) in rows" :key="row.enrolment">
                            <tr class="border-t border-line focus-within:bg-brand-50/50">
                                <td class="px-4 py-1.5 text-sm text-muted" x-text="rowIndex + 1"></td>
                                <th scope="row" class="px-3 py-1.5 text-left font-medium">
                                    <span x-text="row.name"></span>
                                    <span class="block font-mono text-xs font-normal text-muted" x-text="row.admission_no"></span>
                                </th>
                                <template x-for="(component, columnIndex) in components" :key="component.id">
                                    <td class="px-2 py-1.5">
                                        <input
                                            type="text"
                                            inputmode="decimal"
                                            autocomplete="off"
                                            :data-cell="rowIndex + '-' + columnIndex"
                                            :aria-label="component.short + ' for ' + row.name"
                                            :value="value(row, component)"
                                            :disabled="locked"
                                            @change="input(row, component, $event)"
                                            @keydown.enter="move($event, rowIndex, columnIndex, 1)"
                                            @keydown.arrow-down="move($event, rowIndex, columnIndex, 1)"
                                            @keydown.arrow-up="move($event, rowIndex, columnIndex, -1)"
                                            @paste="paste($event, rowIndex, columnIndex)"
                                            @focus="$event.target.select()"
                                            class="w-full rounded-lg border border-line bg-canvas px-2 py-1.5 text-center font-mono text-[15px] focus:border-brand-600 focus:bg-white focus:ring-2 focus:ring-brand-100 focus:outline-none aria-invalid:border-danger aria-invalid:bg-danger-50 disabled:opacity-60"
                                        >
                                    </td>
                                </template>
                                <td class="px-3 py-1.5 text-center font-bold" x-text="total(row) ?? '—'"></td>
                                <td class="px-4 py-1.5 text-center">
                                    <span x-show="grade(row)" class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-600" x-text="grade(row)"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <p class="m-0 border-t border-line px-5 py-3 text-sm text-muted">
                Tip: press Enter to go to the next student. You can paste a column of scores straight from Excel.
            </p>
        </x-card>
    </div>
</x-layouts.app>
