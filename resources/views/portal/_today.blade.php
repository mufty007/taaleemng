{{-- Today's lessons for a student, the current one highlighted. Expects $todayLessons. --}}
<section aria-labelledby="today-title" class="flex flex-col gap-3">
    <h2 id="today-title" class="m-0 text-sm font-semibold tracking-wide text-muted uppercase">Today’s lessons · {{ now()->format('l') }}</h2>
    <ol class="-mx-1 m-0 flex list-none gap-2 overflow-x-auto px-1 pb-1">
        @foreach ($todayLessons as $lesson)
            @php($now = $lesson->period->isNow())
            <li @class(['flex w-40 flex-none flex-col gap-0.5 rounded-2xl border px-4 py-3', 'border-brand-600 bg-brand-600 text-white' => $now, 'border-line bg-white' => ! $now])>
                <span @class(['text-xs', 'text-brand-100' => $now, 'text-muted' => ! $now])>{{ $lesson->period->times() }}{{ $now ? ' · now' : '' }}</span>
                <span class="truncate font-semibold">{{ $lesson->classSubject->subject->name }}</span>
                <span @class(['truncate text-xs', 'text-brand-100' => $now, 'text-muted' => ! $now])>{{ $lesson->classSubject->teacher?->fullName() }}</span>
            </li>
        @endforeach
    </ol>
</section>
