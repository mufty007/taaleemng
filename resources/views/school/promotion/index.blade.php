<x-layouts.app title="Promotion">
    <x-page-header kicker="End of session" title="Promotion" :description="$to ? 'Move each class from '.$from->name.' into '.$to->name.', using the decisions on the third-term report cards.' : 'Move each class into the next session at the end of the year.'">
        <x-slot:actions>
            <form method="GET">
                <label for="from" class="sr-only">Session to move from</label>
                <select id="from" name="from" class="field w-56 bg-white py-2" x-data x-on:change="$el.form.requestSubmit()">
                    @foreach ($sessions as $id => $name)<option value="{{ $id }}" @selected($from->id === $id)>From {{ $name }}</option>@endforeach
                </select>
            </form>
        </x-slot:actions>
    </x-page-header>

    @if (! $to)
        @php($nextYear = $from->starts_on->year + 1)
        <x-card>
            <x-empty-state icon="trending-up" title="Start the {{ $nextYear }}/{{ $nextYear + 1 }} session first" body="Students move into the next session, so it needs to exist. Creating it does not switch the school over; you make it current on Sessions & terms when the new year begins.">
                <form method="POST" action="{{ route('school.sessions.store') }}">
                    @csrf
                    <input type="hidden" name="start_year" value="{{ $nextYear }}">
                    <x-button icon="plus">Start {{ $nextYear }}/{{ $nextYear + 1 }}</x-button>
                </form>
            </x-empty-state>
        </x-card>
    @else
        <div class="flex flex-wrap items-start gap-3 rounded-2xl border border-line bg-white px-5 py-4 text-[15px]">
            <x-lucide-info class="mt-0.5 size-5 flex-none text-brand-600" aria-hidden="true" />
            <p class="m-0 min-w-0 flex-1 text-muted">
                Open a class, check each student’s decision (filled from the principal’s choice on the report card, or suggested from the session average), then move the class.
                You can open a class again to correct it; nobody is enrolled twice.
            </p>
        </div>

        <x-card :padding="false">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] border-collapse text-left text-[15px]">
                    <thead>
                        <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                            <th scope="col" class="px-5 py-3 font-semibold">Class in {{ $from->name }}</th>
                            <th scope="col" class="px-3 py-3 text-right font-semibold">Students</th>
                            <th scope="col" class="px-3 py-3 text-right font-semibold">Decisions on report cards</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Moved into {{ $to->name }}</th>
                            <th scope="col" class="px-5 py-3"><span class="sr-only">Open</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($arms as $arm)
                            @php($row = $progress[$arm->id] ?? ['decided' => 0, 'done' => 0])
                            <tr class="border-t border-line hover:bg-canvas">
                                <td class="px-5 py-3 font-semibold">{{ $arm->displayName() }}</td>
                                <td class="px-3 py-3 text-right">{{ $arm->enrolments_count }}</td>
                                <td class="px-3 py-3 text-right text-muted">{{ $row['decided'] }}/{{ $arm->enrolments_count }}</td>
                                <td class="px-3 py-3">
                                    @if ($row['done'] >= $arm->enrolments_count)
                                        <x-badge tone="naija">Done</x-badge>
                                    @elseif ($row['done'] > 0)
                                        <x-badge tone="gold">{{ $row['done'] }} of {{ $arm->enrolments_count }}</x-badge>
                                    @else
                                        <x-badge>Not yet</x-badge>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <x-button :variant="$row['done'] >= $arm->enrolments_count ? 'ghost' : 'soft'" size="sm" icon="arrow-right" :href="route('school.promotion.show', [$arm, 'from' => $from->id])">
                                        {{ $row['done'] >= $arm->enrolments_count ? 'Review' : 'Promote' }}
                                    </x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
</x-layouts.app>
