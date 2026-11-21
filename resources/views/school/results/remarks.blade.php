@php
    use App\Models\ReportTrait;
    $locked = $term->isClosed();
@endphp

<x-layouts.app :title="$arm->displayName().' remarks'">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.results.index', ['term' => $term->id]) }}" class="hover:text-ink">Results</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <a href="{{ route('school.results.show', [$arm, 'term' => $term->id]) }}" class="hover:text-ink">{{ $arm->displayName() }}</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">Remarks</span>
    </div>

    <div
        x-data="remarksBook({ rows: @js($rows), url: @js(route('school.remarks.update', [$arm, '__ENROLMENT__', 'term' => $term->id])), locked: @js($locked) })"
        class="flex flex-col gap-5"
    >
        <x-page-header :kicker="$term->name.' · '.$term->academicSession->name" :title="$arm->displayName().' report remarks'">
            <x-slot:description>
                <span x-text="completed"></span> of {{ $rows->count() }} report cards complete · Form teacher: {{ $arm->formTeacher?->fullName() ?? 'not assigned' }}
            </x-slot:description>
            <x-slot:actions>
                <form method="GET">
                    <label for="term" class="sr-only">Term</label>
                    <select id="term" name="term" class="field w-60 bg-white py-2" x-data x-on:change="$el.form.requestSubmit()">
                        @foreach ($terms as $id => $label)<option value="{{ $id }}" @selected($term->id === $id)>{{ $label }}</option>@endforeach
                    </select>
                </form>
                @unless ($locked)
                    <form method="POST" action="{{ route('school.remarks.autofill', [$arm, 'term' => $term->id]) }}">
                        @csrf
                        <x-button variant="secondary" icon="sparkles">Fill empty comments</x-button>
                    </form>
                @endunless
                <x-button variant="soft" icon="table-2" :href="route('school.results.show', [$arm, 'term' => $term->id])">Broadsheet</x-button>
            </x-slot:actions>
        </x-page-header>

        @error('term')<div class="rounded-2xl bg-danger-50 px-5 py-3 text-[15px] font-medium text-danger" role="alert">{{ $message }}</div>@enderror

        @if ($locked)
            <div class="rounded-2xl border border-gold-200 bg-gold-50 px-5 py-3 text-[15px] text-gold"><b>{{ $term->name }} is closed.</b> Report cards can be viewed but not changed.</div>
        @elseif (! $term->school_days)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-line bg-white px-5 py-3 text-[15px]">
                <span><b>How many times did the school open this term?</b> <span class="text-muted">Set it so report cards show attendance as “58 out of 62”.</span></span>
                @can('academics.manage')
                    <x-button variant="soft" size="sm" icon="calendar" :href="route('school.sessions.index')">Set on Sessions &amp; terms</x-button>
                @endcan
            </div>
        @endif

        <x-card :padding="false">
            @if ($rows->isEmpty())
                <x-empty-state icon="users" title="No students in this class" body="Students enrolled in {{ $arm->displayName() }} for {{ $term->academicSession->name }} appear here." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] border-collapse text-left text-[15px]">
                        <thead>
                            <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                                <th scope="col" class="w-10 px-4 py-3 font-semibold">#</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Student</th>
                                <th scope="col" class="px-3 py-3 text-right font-semibold">Average</th>
                                <th scope="col" class="px-3 py-3 text-right font-semibold">Pos.</th>
                                <th scope="col" class="px-3 py-3 text-right font-semibold">Present</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Form teacher’s comment</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                                <th scope="col" class="px-4 py-3"><span class="sr-only">Edit</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, rowIndex) in rows" :key="row.enrolment">
                                <tr class="cursor-pointer border-t border-line hover:bg-canvas" x-on:click="open(rowIndex)">
                                    <td class="px-4 py-3 text-sm text-muted" x-text="rowIndex + 1"></td>
                                    <th scope="row" class="px-3 py-3 text-left font-medium">
                                        <span x-text="row.name"></span>
                                        <span class="block font-mono text-xs font-normal text-muted" x-text="row.admission_no"></span>
                                    </th>
                                    <td class="px-3 py-3 text-right font-semibold" x-text="row.average ?? '—'"></td>
                                    <td class="px-3 py-3 text-right text-brand-600" x-text="row.position ?? '—'"></td>
                                    <td class="px-3 py-3 text-right" x-text="row.days_present ?? '—'"></td>
                                    <td class="max-w-72 px-3 py-3 text-sm">
                                        <span class="line-clamp-2" :class="row.teacher_comment ? '' : 'text-muted'" x-text="row.teacher_comment || 'No comment yet'"></span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <span x-show="row.complete" class="inline-flex items-center gap-1 rounded-full bg-naija-50 px-2.5 py-1 text-xs font-semibold text-naija-700">Done</span>
                                        <span x-show="! row.complete" class="inline-flex items-center gap-1 rounded-full bg-chip px-2.5 py-1 text-xs font-semibold text-muted">To do</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-ink" x-on:click.stop="open(rowIndex)" :aria-label="'Edit remarks for ' + row.name">
                                            {{ $locked ? 'View' : 'Edit' }} <x-lucide-chevron-right class="size-4" aria-hidden="true" />
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-drawer name="remarks" title="Remarks" title-expression="current?.name" width="sm:max-w-2xl">
            <template x-if="current">
                <div class="flex flex-col gap-6">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="rounded-xl border border-line px-4 py-3"><div class="text-xs text-muted">Average</div><div class="text-lg font-bold" x-text="current.average ?? '—'"></div></div>
                        <div class="rounded-xl border border-line px-4 py-3"><div class="text-xs text-muted">Position</div><div class="text-lg font-bold text-brand-600" x-text="current.position ?? '—'"></div></div>
                        @if ($isFinalTerm)
                            <div class="rounded-xl border border-line px-4 py-3"><div class="text-xs text-muted">Session average</div><div class="text-lg font-bold" x-text="current.cumulative ?? '—'"></div></div>
                        @else
                            <div class="rounded-xl border border-line px-4 py-3"><div class="text-xs text-muted">Admission no.</div><div class="truncate font-mono text-sm font-semibold leading-7" x-text="current.admission_no"></div></div>
                        @endif
                    </div>

                    <div>
                        <label for="days_present" class="mb-1.5 block text-sm font-semibold">Times present</label>
                        <div class="flex items-center gap-3">
                            <input id="days_present" type="number" min="0" @if ($term->school_days) max="{{ $term->school_days }}" @endif inputmode="numeric" class="field w-28" x-model="form.days_present" :disabled="locked">
                            <span class="text-sm text-muted">{{ $term->school_days ? 'out of '.$term->school_days.' times the school opened' : 'times the school opened is not set yet' }}</span>
                        </div>
                    </div>

                    @foreach ($traits->groupBy(fn ($trait) => $trait->category->value) as $group)
                        <fieldset class="m-0 border-0 p-0">
                            <legend class="mb-2 text-sm font-semibold">{{ $group->first()->category->label() }}</legend>
                            <div class="divide-y divide-line rounded-xl border border-line">
                                @foreach ($group as $trait)
                                    <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2">
                                        <span class="text-[15px]">{{ $trait->name }}</span>
                                        <div class="flex gap-1" role="group" aria-label="{{ $trait->name }} rating">
                                            @foreach (ReportTrait::RATINGS as $rating => $label)
                                                <button
                                                    type="button"
                                                    class="grid size-9 place-items-center rounded-lg border text-sm font-semibold transition disabled:opacity-60"
                                                    :class="form.traits[{{ $trait->id }}] === {{ $rating }} ? 'border-brand-600 bg-brand-600 text-white' : 'border-line bg-white text-muted hover:border-brand hover:text-brand-600'"
                                                    :aria-pressed="form.traits[{{ $trait->id }}] === {{ $rating }}"
                                                    :disabled="locked"
                                                    x-on:click="rate({{ $trait->id }}, {{ $rating }})"
                                                    title="{{ $label }}"
                                                >{{ $rating }}</button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                    <p class="m-0 text-xs text-muted">{{ collect(ReportTrait::RATINGS)->map(fn ($label, $rating) => $rating.' '.$label)->join(' · ') }}</p>

                    <div>
                        <label for="teacher_comment" class="mb-1.5 block text-sm font-semibold">Form teacher’s comment</label>
                        <textarea id="teacher_comment" rows="3" maxlength="500" class="field resize-y" x-model="form.teacher_comment" :disabled="locked"></textarea>
                        <p class="m-0 mt-1.5 text-xs text-muted" x-show="! locked && ! current.scores_complete">Suggested comments appear once all of this student’s scores are in.</p>
                        <div class="mt-2 flex flex-wrap gap-1.5" x-show="! locked && current.suggestions.teacher.length">
                            <template x-for="suggestion in current.suggestions.teacher" :key="suggestion">
                                <button type="button" class="rounded-lg bg-brand-50 px-2.5 py-1.5 text-left text-xs text-brand-600 hover:bg-brand-100" x-on:click="form.teacher_comment = suggestion" x-text="suggestion"></button>
                            </template>
                        </div>
                    </div>

                    <div>
                        <label for="principal_comment" class="mb-1.5 block text-sm font-semibold">Principal’s comment</label>
                        @if ($canApprove)
                            <textarea id="principal_comment" rows="2" maxlength="500" class="field resize-y" x-model="form.principal_comment" :disabled="locked"></textarea>
                            <div class="mt-2 flex flex-wrap gap-1.5" x-show="! locked && current.suggestions.principal.length">
                                <template x-for="suggestion in current.suggestions.principal" :key="suggestion">
                                    <button type="button" class="rounded-lg bg-brand-50 px-2.5 py-1.5 text-left text-xs text-brand-600 hover:bg-brand-100" x-on:click="form.principal_comment = suggestion" x-text="suggestion"></button>
                                </template>
                            </div>
                        @else
                            <p id="principal_comment" class="m-0 rounded-xl bg-canvas px-4 py-3 text-[15px]" :class="form.principal_comment ? '' : 'text-muted'" x-text="form.principal_comment || 'The principal adds this.'"></p>
                        @endif
                    </div>

                    @if ($isFinalTerm)
                        <div>
                            <label for="promotion_decision" class="mb-1.5 block text-sm font-semibold">End of session</label>
                            @if ($canApprove)
                                <select id="promotion_decision" class="field" x-model="form.promotion_decision" :disabled="locked">
                                    <option value="">Not decided</option>
                                    @foreach ($decisions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                            @else
                                <p class="m-0 rounded-xl bg-canvas px-4 py-3 text-[15px]" x-text="(@js($decisions))[current.promotion_decision] ?? 'The principal decides this.'"></p>
                            @endif
                            <p class="m-0 mt-1.5 text-xs text-muted" x-show="current.suggested_decision">
                                Suggested from the session average: <b x-text="(@js($decisions))[current.suggested_decision]"></b>
                            </p>
                        </div>
                    @endif

                </div>
            </template>

            <x-slot:footer>
                <p class="m-0 mr-auto text-sm font-medium text-danger" x-show="error" x-text="error" role="alert"></p>
                @if ($locked)
                    <x-button variant="secondary" type="button" x-on:click="$dispatch('close-drawer', 'remarks')">Close</x-button>
                @else
                    <x-button variant="secondary" type="button" x-on:click="save(false)" x-bind:disabled="saving">Save</x-button>
                    <x-button type="button" icon="arrow-right" x-on:click="save(true)" x-bind:disabled="saving" x-show="hasNext">Save &amp; next</x-button>
                @endif
            </x-slot:footer>
        </x-drawer>
    </div>
</x-layouts.app>
