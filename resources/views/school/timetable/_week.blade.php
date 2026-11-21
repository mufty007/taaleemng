{{--
    A week of lessons, read-only. Expects $periods, $grid ([period id][day] => entry) and $show:
    'class' (each cell names the subject and teacher) or 'teacher' (subject and class).
    Optional $print to drop screen-only touches.
--}}
@php
    $print ??= false;
    $today = now()->dayOfWeekIso;
@endphp

@if ($periods->isEmpty())
    <x-empty-state icon="clock" title="No bell schedule yet" body="Set the school day’s periods and breaks first." />
@else
    <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] table-fixed border-collapse text-left text-sm">
            <thead>
                <tr>
                    <th scope="col" class="w-28 border-b-2 border-brand-600 px-3 py-2 text-xs font-semibold text-muted uppercase">Time</th>
                    @foreach (\App\Models\Period::DAYS as $day => $dayName)
                        <th scope="col" @class(['border-b-2 border-brand-600 px-3 py-2 text-xs font-semibold uppercase', 'bg-brand-50 text-brand-700' => ! $print && $day === $today, 'text-muted' => $print || $day !== $today])>{{ $dayName }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($periods as $period)
                    @if (! $period->kind->isLesson())
                        <tr>
                            <th scope="row" class="border-b border-line px-3 py-1.5 align-top text-xs font-normal text-muted">{{ $period->times() }}</th>
                            <td colspan="5" class="border-b border-line bg-chip px-3 py-1.5 text-center text-xs font-semibold tracking-[0.2em] text-muted uppercase">{{ $period->name }}</td>
                        </tr>
                    @else
                        <tr @class(['bg-gold-50/60' => ! $print && $period->isNow()])>
                            <th scope="row" class="border-b border-line px-3 py-2 align-top">
                                <span class="block text-sm font-semibold">{{ $period->name }}</span>
                                <span class="block text-xs font-normal text-muted">{{ $period->times() }}</span>
                            </th>
                            @foreach (array_keys(\App\Models\Period::DAYS) as $day)
                                @php($entry = $grid[$period->id][$day] ?? null)
                                <td @class(['border-b border-l border-line px-2 py-1.5 align-top', 'bg-brand-50/50' => ! $print && $day === $today])>
                                    @if ($entry)
                                        <span class="block truncate font-semibold">{{ $entry->classSubject->subject->name }}</span>
                                        <span class="block truncate text-xs text-muted">{{ $show === 'teacher' ? $entry->classArm->displayName() : ($entry->classSubject->teacher?->fullName() ?? 'No teacher') }}</span>
                                    @else
                                        <span class="text-xs text-placeholder">Free</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
@endif
