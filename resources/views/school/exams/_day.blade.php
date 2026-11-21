{{-- One exam day. Expects $date, $papers; optional $canManage and $print. --}}
@php
    $canManage ??= false;
    $print ??= false;
@endphp

<section class="break-inside-avoid">
    <h3 class="m-0 mb-2 flex items-center gap-2 text-base font-semibold">
        <span class="rounded-lg bg-brand-600 px-2 py-0.5 text-xs font-bold tracking-wide text-white uppercase">{{ \Illuminate\Support\Carbon::parse($date)->format('D') }}</span>
        {{ \Illuminate\Support\Carbon::parse($date)->format('l j F Y') }}
    </h3>
    <table class="w-full border-collapse text-left text-sm">
        <thead>
            <tr class="border-b-2 border-brand-200 text-xs font-semibold text-muted uppercase">
                <th scope="col" class="w-36 py-2 pr-3">Time</th>
                <th scope="col" class="px-3 py-2">Class</th>
                <th scope="col" class="px-3 py-2">Subject</th>
                <th scope="col" class="px-3 py-2">Venue</th>
                <th scope="col" class="px-3 py-2">Invigilator</th>
                @if ($canManage && ! $print)<th scope="col" class="py-2 pl-3 text-right"><span class="sr-only">Actions</span></th>@endif
            </tr>
        </thead>
        <tbody>
            @foreach ($papers as $paper)
                <tr class="border-b border-line">
                    <td class="py-2 pr-3 whitespace-nowrap"><span class="font-semibold">{{ $paper->times() }}</span> <span class="text-xs text-muted">({{ $paper->duration_minutes >= 60 ? rtrim(rtrim(number_format($paper->duration_minutes / 60, 1), '0'), '.').'h' : $paper->duration_minutes.'m' }})</span></td>
                    <td class="px-3 py-2 font-medium whitespace-nowrap">{{ $paper->classLevel->name }}</td>
                    <td class="px-3 py-2">{{ $paper->subject->name }}@if ($paper->notes)<span class="block text-xs text-muted">{{ $paper->notes }}</span>@endif</td>
                    <td class="px-3 py-2 text-muted">{{ $paper->venue ?? '—' }}</td>
                    <td class="px-3 py-2 text-muted">{{ $paper->invigilator?->fullName() ?? '—' }}</td>
                    @if ($canManage && ! $print)
                        <td class="py-2 pl-3">
                            <div class="flex justify-end gap-0.5">
                                <button type="button" class="grid size-8 place-items-center rounded-lg text-muted hover:bg-chip hover:text-ink" aria-label="Edit {{ $paper->subject->name }} for {{ $paper->classLevel->name }}" x-data x-on:click="$dispatch('edit-paper', { action: @js(route('school.exams.update', $paper)), values: @js(['class_level_id' => (string) $paper->class_level_id, 'subject_id' => (string) $paper->subject_id, 'date' => $paper->starts_at->toDateString(), 'time' => $paper->starts_at->format('H:i'), 'duration_minutes' => $paper->duration_minutes, 'venue' => $paper->venue, 'invigilator_id' => (string) ($paper->invigilator_id ?? ''), 'notes' => $paper->notes]) })"><x-lucide-pencil class="size-3.5" aria-hidden="true" /></button>
                                <form method="POST" action="{{ route('school.exams.destroy', $paper) }}" x-data x-on:submit="if (! confirm('Remove this paper?')) $event.preventDefault()">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="grid size-8 place-items-center rounded-lg text-muted hover:bg-danger-50 hover:text-danger" aria-label="Remove {{ $paper->subject->name }} for {{ $paper->classLevel->name }}"><x-lucide-trash-2 class="size-3.5" aria-hidden="true" /></button>
                                </form>
                            </div>
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</section>
