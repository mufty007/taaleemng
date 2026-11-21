{{-- This term's register for one student. Expects $attendance (summary or null) and $recentAttendance. --}}
<div class="flex flex-col gap-4">
    @if ($attendance && $attendance['days'] > 0)
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach (['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'excused' => 'Excused'] as $key => $label)
                <div class="rounded-2xl border border-line bg-white px-4 py-3">
                    <div class="text-sm text-muted">{{ $label }}</div>
                    <div class="text-2xl font-bold">{{ $attendance[$key] }}</div>
                </div>
            @endforeach
        </div>
        <x-card :padding="false">
            <h2 class="m-0 border-b border-line px-5 py-3 text-base font-semibold">Recent days</h2>
            <ul class="m-0 list-none divide-y divide-line p-0">
                @foreach ($recentAttendance as $record)
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-[15px]">
                        <span>{{ $record->date->format('l j M') }}</span>
                        <span class="flex items-center gap-2">
                            @if ($record->note)<span class="text-sm text-muted">{{ $record->note }}</span>@endif
                            <x-badge :tone="$record->status->tone()">{{ $record->status->label() }}</x-badge>
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @else
        <x-card><x-empty-state icon="clipboard-check" title="No register yet this term" body="Once the form teacher takes the register, each day shows here." /></x-card>
    @endif
</div>
