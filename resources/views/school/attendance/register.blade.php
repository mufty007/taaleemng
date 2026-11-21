<x-layouts.app :title="'Register · '.$arm->displayName()">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        @can('attendance.view')
            <a href="{{ route('school.attendance.index', ['date' => $date->toDateString()]) }}" class="hover:text-ink">Attendance</a>
            <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        @endcan
        <span class="text-ink">{{ $arm->displayName() }}</span>
    </div>

    <div
        x-data="attendanceRegister({ rows: @js($rows), url: @js(route('school.attendance.save', $arm)), date: @js($date->toDateString()), locked: @js($locked) })"
        class="flex flex-col gap-5"
    >
        <x-page-header :kicker="$term ? $term->name.' · '.$term->academicSession->name : 'Outside term'" :title="$arm->displayName().' register'" :description="$date->format('l j F Y').' · Form teacher: '.($arm->formTeacher?->fullName() ?? 'not assigned')">
            <x-slot:actions>
                <div class="flex items-center gap-2 rounded-xl border border-line bg-white px-3.5 py-2 text-sm" aria-live="polite">
                    <template x-if="saving"><span class="text-muted">Saving…</span></template>
                    <template x-if="! saving && savedAt"><span class="text-naija" x-text="'Saved · ' + savedAt"></span></template>
                    <template x-if="! saving && ! savedAt"><span class="text-muted"><span x-text="rows.length - unmarked"></span> of {{ count($rows) }} marked</span></template>
                </div>
                @unless ($locked)
                    <x-button variant="brand" icon="check-check" type="button" x-on:click="markAllPresent()" x-bind:disabled="! unmarked">Mark the rest present</x-button>
                @endunless
            </x-slot:actions>
        </x-page-header>

        <nav class="flex flex-wrap items-center gap-2" aria-label="Choose a day">
            <a href="{{ route('school.attendance.register', [$arm, 'date' => $week->first()->copy()->subWeek()->toDateString()]) }}" class="grid size-10 place-items-center rounded-xl border border-line bg-white text-muted hover:text-ink" aria-label="Previous week"><x-lucide-chevron-left class="size-5" aria-hidden="true" /></a>
            @foreach ($week as $day)
                <a href="{{ route('school.attendance.register', [$arm, 'date' => $day->toDateString()]) }}" @class([
                    'flex min-w-20 flex-col items-center rounded-xl border px-3 py-1.5 text-sm transition',
                    'border-ink bg-ink text-white' => $day->isSameDay($date),
                    'border-line bg-white text-muted hover:text-ink' => ! $day->isSameDay($date),
                    'opacity-50' => $day->isFuture(),
                ]) @if ($day->isSameDay($date)) aria-current="date" @endif>
                    <span class="font-semibold">{{ $day->format('D') }}</span>
                    <span class="flex items-center gap-1 text-xs">{{ $day->format('j M') }} @if ($marked->has($day->toDateString()))<span class="size-1.5 rounded-full bg-naija" title="Taken"></span>@endif</span>
                </a>
            @endforeach
            <a href="{{ route('school.attendance.register', [$arm, 'date' => $week->first()->copy()->addWeek()->toDateString()]) }}" class="grid size-10 place-items-center rounded-xl border border-line bg-white text-muted hover:text-ink" aria-label="Next week"><x-lucide-chevron-right class="size-5" aria-hidden="true" /></a>
        </nav>

        @if ($locked)
            <div class="rounded-2xl border border-gold-200 bg-gold-50 px-5 py-3 text-[15px] text-gold">
                @if (! $term) {{ $date->format('j F') }} is not within a term. @elseif ($term->isClosed()) {{ $term->name }} is closed; the register can be viewed but not changed. @else The register can’t be taken for a future day. @endif
            </div>
        @endif
        <div x-show="error" x-cloak class="rounded-2xl bg-danger-50 px-5 py-3 text-[15px] font-medium text-danger" role="alert" x-text="error"></div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($statuses as $status)
                <div class="rounded-2xl border border-line bg-white px-4 py-3">
                    <div class="text-sm text-muted">{{ $status->label() }}</div>
                    <div class="text-2xl font-bold" x-text="count(@js($status->value))">0</div>
                </div>
            @endforeach
        </div>

        <x-card :padding="false">
            @if (count($rows) === 0)
                <x-empty-state icon="users" title="No students in this class" />
            @else
                <ul class="m-0 list-none divide-y divide-line p-0">
                    <template x-for="(row, index) in rows" :key="row.enrolment">
                        <li class="flex flex-wrap items-center gap-3 px-5 py-2.5">
                            <span class="w-6 text-sm text-muted" x-text="index + 1"></span>
                            <div class="min-w-48 flex-1">
                                <div class="font-medium" x-text="row.name"></div>
                                <div class="font-mono text-xs text-muted"><span x-text="row.admission_no"></span><span x-show="row.rate !== null" x-text="' · ' + row.rate + '% this term'"></span></div>
                            </div>
                            <div class="flex gap-1" role="radiogroup" :aria-label="'Attendance for ' + row.name">
                                @foreach ($statuses as $status)
                                    @php
                                        $on = match ($status->tone()) {
                                            'naija' => 'border-naija bg-naija text-white',
                                            'gold' => 'border-gold bg-gold text-white',
                                            'danger' => 'border-danger bg-danger text-white',
                                            default => 'border-sky bg-sky text-white',
                                        };
                                    @endphp
                                    <button
                                        type="button"
                                        role="radio"
                                        class="grid size-10 place-items-center rounded-lg border text-sm font-bold transition disabled:opacity-60"
                                        :class="row.status === @js($status->value) ? @js($on) : 'border-line bg-white text-muted hover:border-ink hover:text-ink'"
                                        :aria-checked="row.status === @js($status->value)"
                                        :disabled="locked"
                                        x-on:click="mark(row, @js($status->value))"
                                        title="{{ $status->label() }}"
                                    >{{ $status->short() }}</button>
                                @endforeach
                            </div>
                            <label class="sr-only" :for="'note-' + row.enrolment">Note</label>
                            <input :id="'note-' + row.enrolment" type="text" maxlength="200" placeholder="Note" class="field w-40 py-1.5 text-sm" x-model="row.note" x-on:change="note(row)" x-show="row.status && row.status !== 'present'" :disabled="locked">
                        </li>
                    </template>
                </ul>
            @endif
        </x-card>
        <p class="m-0 text-sm text-muted">P present · L late (counts as present) · A absent · E excused. Changes save as you tap.</p>
    </div>
</x-layouts.app>
