<x-layouts.app title="Timetable">
    <x-page-header kicker="Academics" title="Timetable" :description="$canManage ? 'Build each class’s week. Pick a subject for each period, or let Ta’aleem fill the week for you. A teacher is never put in two classes at once.' : 'Each class’s week of lessons.'">
        <x-slot:actions>
            @if ($canManage && $periods->isNotEmpty() && $arms->isNotEmpty())
                <form method="POST" action="{{ route('school.timetable.autofill-all') }}" data-confirm="Fill empty lesson periods for every class? Existing lessons stay; teachers are never double-booked." data-confirm-title="Fill all classes?" data-confirm-action="Fill all" data-confirm-tone="brand">
                    @csrf
                    <x-button variant="brand" icon="wand-sparkles">Fill all classes</x-button>
                </form>
            @endif
            @if ($arm)
                <x-button data-native variant="secondary" icon="printer" :href="route('school.timetable.print', ['kind' => 'class', 'ulid' => $arm->ulid])">Print</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>


    @include('school.timetable._tabs', ['current' => 'classes'])

    @if ($periods->isEmpty())
        <x-card>
            <x-empty-state icon="clock" title="Set the school day first" body="Add the periods and breaks of a school day. You can start from a standard day and change the times.">
                @if ($canManage)
                    <form method="POST" action="{{ route('school.timetable.periods.defaults') }}">
                        @csrf
                        <x-button icon="sparkles">Use a standard school day</x-button>
                    </form>
                @endif
            </x-empty-state>
        </x-card>
    @elseif (! $arm)
        <x-card><x-empty-state icon="layers" title="No classes with students yet" /></x-card>
    @else
        <div class="flex flex-wrap items-center gap-3">
            <form method="GET" class="flex items-center gap-2">
                <label for="class" class="text-sm font-semibold">Class</label>
                <select id="class" name="class" class="field w-48 bg-white py-2" x-data x-on:change="$el.form.requestSubmit()">
                    @foreach ($arms as $option)
                        <option value="{{ $option->ulid }}" @selected($arm->is($option))>{{ $option->displayName() }}</option>
                    @endforeach
                </select>
            </form>
            @if ($canManage)
                <div class="ml-auto flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('school.timetable.autofill', $arm) }}">
                        @csrf
                        <x-button variant="brand" icon="wand-sparkles">Fill the empty periods</x-button>
                    </form>
                    <form method="POST" action="{{ route('school.timetable.clear', $arm) }}" data-confirm="{{ 'Clear every lesson from '.$arm->displayName().'’s timetable?' }}" data-confirm-title="Clear timetable?" data-confirm-action="Clear">
                        @csrf
                        <x-button variant="ghost" icon="eraser">Clear</x-button>
                    </form>
                </div>
            @endif
        </div>

        @if ($canManage)
            @php
                $slots = [];
                foreach ($grid as $periodId => $days) {
                    foreach ($days as $day => $entry) {
                        $slots["{$day}-{$periodId}"] = (string) $entry->class_subject_id;
                    }
                }
            @endphp
            <div
                x-data="timetableEditor({ url: @js(route('school.timetable.slots', $arm)), slots: @js((object) $slots) })"
                class="grid grid-cols-1 items-start gap-5 2xl:grid-cols-[minmax(0,1fr)_18rem]"
            >
                <x-card :padding="false">
                    <div x-show="error" x-cloak class="m-4 rounded-xl bg-danger-50 px-4 py-2.5 text-sm font-medium text-danger" role="alert" x-text="error"></div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[860px] table-fixed border-collapse text-left text-sm">
                            <thead>
                                <tr>
                                    <th scope="col" class="w-28 border-b border-line px-3 py-3 text-xs font-semibold text-muted uppercase">Time</th>
                                    @foreach (\App\Models\Period::DAYS as $dayName)
                                        <th scope="col" class="border-b border-line px-2 py-3 text-xs font-semibold text-muted uppercase">{{ $dayName }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($periods as $period)
                                    @if (! $period->kind->isLesson())
                                        <tr>
                                            <th scope="row" class="border-b border-line px-3 py-1.5 text-xs font-normal text-muted">{{ $period->times() }}</th>
                                            <td colspan="5" class="border-b border-line bg-chip px-3 py-1.5 text-center text-xs font-semibold tracking-[0.2em] text-muted uppercase">{{ $period->name }}</td>
                                        </tr>
                                    @else
                                        <tr>
                                            <th scope="row" class="border-b border-line px-3 py-2 align-top">
                                                <span class="block font-semibold">{{ $period->name }}</span>
                                                <span class="block text-xs font-normal text-muted">{{ $period->times() }}</span>
                                            </th>
                                            @foreach (array_keys(\App\Models\Period::DAYS) as $day)
                                                @php($key = "{$day}-{$period->id}")
                                                <td class="border-b border-l border-line p-1.5 align-top">
                                                    <label class="sr-only" for="slot-{{ $key }}">{{ \App\Models\Period::DAYS[$day] }}, {{ $period->name }}</label>
                                                    <select
                                                        id="slot-{{ $key }}"
                                                        class="w-full rounded-lg border px-2 py-1.5 text-[13px] transition focus:border-brand-600 focus:ring-2 focus:ring-brand-100 focus:outline-none"
                                                        :class="slots[@js($key)] ? 'border-brand-200 bg-brand-50 font-semibold text-ink' : 'border-dashed border-line bg-white text-muted'"
                                                        x-model="slots[@js($key)]"
                                                        x-on:change="save({{ $day }}, {{ $period->id }}, $event.target)"
                                                    >
                                                        <option value="">Free</option>
                                                        @foreach ($classSubjects as $classSubject)
                                                            <option value="{{ $classSubject->id }}">{{ $classSubject->subject->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <span class="mt-0.5 block truncate px-1 text-[11px] text-muted" x-text="teachers[slots[@js($key)]] ?? ''"></span>
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="m-0 border-t border-line px-5 py-3 text-sm text-muted" aria-live="polite">
                        <span x-show="saving">Saving…</span>
                        <span x-show="! saving && savedAt" x-text="'Saved · ' + savedAt"></span>
                        <span x-show="! saving && ! savedAt">Changes save as you choose.</span>
                    </p>
                </x-card>

                <x-card :padding="false">
                    <h2 class="m-0 border-b border-line px-5 py-3 text-base font-semibold">Lessons a week</h2>
                    <ul class="m-0 list-none p-0 text-sm">
                        @foreach ($load as $row)
                            @php($id = (string) $row['classSubject']->id)
                            <li class="flex items-center gap-2 border-b border-line px-5 py-2 last:border-b-0">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium">{{ $row['classSubject']->subject->name }}</span>
                                    <span class="block truncate text-xs text-muted">{{ $row['classSubject']->teacher?->fullName() ?? 'No teacher yet' }}</span>
                                </span>
                                <span class="font-mono text-xs font-semibold" :class="count(@js($id)) >= {{ $row['target'] }} ? 'text-naija-700' : 'text-gold'"><span x-text="count(@js($id))">{{ $row['planned'] }}</span>/{{ $row['target'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
                <script type="application/json" data-teachers>@json($classSubjects->mapWithKeys(fn ($classSubject) => [(string) $classSubject->id => $classSubject->teacher?->fullName() ?? 'No teacher yet']))</script>
            </div>
        @else
            <x-card :padding="false" class="p-2">
                @include('school.timetable._week', ['show' => 'class'])
            </x-card>
        @endif
    @endif
</x-layouts.app>
