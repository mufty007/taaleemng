{{-- A teacher: register, marking and scores first, then what is coming up and their classes. --}}
<div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
    <div class="flex min-w-0 flex-col gap-6">
        @foreach ($registers as $register)
            @php($counts = $register->counts())
            <x-card>
                <div class="flex flex-wrap items-center gap-4">
                    <span @class(['grid size-12 flex-none place-items-center rounded-2xl', 'bg-naija-50 text-naija' => $register->isTaken(), 'bg-gold-50 text-gold' => ! $register->isTaken()])>
                        <x-dynamic-component :component="'lucide-'.($register->isTaken() ? 'clipboard-check' : 'clipboard-list')" class="size-6" aria-hidden="true" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 class="m-0 text-base font-semibold">{{ $register->arm->displayName() }} register · {{ $register->date->isToday() ? 'today' : $register->date->format('l') }}</h2>
                        <p class="m-0 text-sm text-muted">
                            @if ($register->locked && ! $register->term)
                                No school today.
                            @elseif ($register->isTaken())
                                Taken · {{ $counts['present'] + $counts['late'] }} in, {{ $counts['absent'] }} absent{{ $counts['late'] ? ', '.$counts['late'].' late' : '' }}
                            @else
                                {{ array_sum($counts) }} of {{ $register->rows->count() }} marked
                            @endif
                        </p>
                    </div>
                    <x-button :variant="$register->isTaken() ? 'secondary' : 'brand'" icon="clipboard-check" :href="route('school.classes.show', $register->arm).'#register'">{{ $register->isTaken() ? 'View register' : 'Take register' }}</x-button>
                </div>
            </x-card>
        @endforeach

        <x-card :padding="false">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <div>
                    <h2 class="m-0 text-base font-semibold">To mark</h2>
                    <p class="m-0 text-sm text-muted">Work students have handed in that you haven’t marked yet.</p>
                </div>
                <a href="{{ route('school.classwork.index') }}#assignments" class="text-sm font-semibold text-brand-600 hover:text-ink">Classwork</a>
            </div>
            @forelse ($toMark->take(6) as $assignment)
                <a href="{{ route('school.classwork.assignments.show', $assignment) }}" class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-b-0 hover:bg-canvas">
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium">{{ $assignment->title }}</div>
                        <div class="text-sm text-muted">{{ $assignment->classSubject->classArm->displayName() }} · {{ $assignment->classSubject->subject->name }}</div>
                    </div>
                    <x-badge tone="gold">{{ $assignment->unmarked_count }} to mark</x-badge>
                </a>
            @empty
                <p class="m-0 flex items-center gap-2 px-5 py-4 text-sm text-muted"><x-lucide-check class="size-4 text-naija" aria-hidden="true" /> All caught up.</p>
            @endforelse
        </x-card>

        @if ($progress->isNotEmpty())
            <x-card :padding="false">
                <div class="flex items-center justify-between border-b border-line px-5 py-4">
                    <div>
                        <h2 class="m-0 text-base font-semibold">Score entry this term</h2>
                        <p class="m-0 text-sm text-muted">Students with scores in each of your subjects.</p>
                    </div>
                    <a href="{{ route('school.scores.index') }}" class="text-sm font-semibold text-brand-600 hover:text-ink">Score entry</a>
                </div>
                <ul class="m-0 list-none p-0">
                    @foreach ($progress as $row)
                        <li class="border-b border-line last:border-b-0">
                            <a href="{{ route('school.scores.edit', $row['classSubject']) }}" class="grid grid-cols-[minmax(0,1fr)_minmax(0,9rem)_3.5rem] items-center gap-3 px-5 py-3 text-sm hover:bg-canvas">
                                <span class="truncate"><span class="font-medium">{{ $row['classSubject']->subject->name }}</span> <span class="text-muted">· {{ $row['classSubject']->classArm->displayName() }}</span></span>
                                <span class="h-2 overflow-hidden rounded-full bg-chip"><span @class(['block h-full rounded-full', 'bg-naija' => $row['rate'] === 100, 'bg-sky' => $row['rate'] < 100]) style="width: {{ $row['rate'] }}%"></span></span>
                                <span class="text-right font-semibold">{{ $row['scored'] }}/{{ $row['students'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    </div>

    <div class="flex min-w-0 flex-col gap-6">
        <x-card :padding="false">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <h2 class="m-0 text-base font-semibold">Today’s lessons</h2>
                <a href="{{ route('school.timetable.index') }}" class="text-sm font-semibold text-brand-600 hover:text-ink">My timetable</a>
            </div>
            @forelse ($todayLessons as $lesson)
                @php($now = $lesson->period->isNow())
                <a href="{{ route('school.classes.show', $lesson->classArm) }}" @class(['flex items-center gap-3 border-b border-line px-5 py-2.5 last:border-b-0', 'bg-brand-50' => $now, 'hover:bg-canvas' => ! $now])>
                    <span class="w-24 flex-none text-xs text-muted">{{ $lesson->period->times() }}</span>
                    <span class="min-w-0 flex-1 truncate text-sm"><span class="font-semibold">{{ $lesson->classArm->displayName() }}</span> · {{ $lesson->classSubject->subject->name }}</span>
                    @if ($now)<x-badge tone="brand">Now</x-badge>@endif
                </a>
            @empty
                <p class="m-0 px-5 py-4 text-sm text-muted">{{ now()->isWeekend() ? 'No school today.' : 'No lessons on your timetable today.' }}</p>
            @endforelse
        </x-card>

        <x-card :padding="false">
            <div class="border-b border-line px-5 py-4">
                <h2 class="m-0 text-base font-semibold">Coming up</h2>
            </div>
            @forelse ($openQuizzes->take(4) as $quiz)
                <a href="{{ route('school.classwork.quizzes.results', $quiz) }}" class="flex items-center gap-3 border-b border-line px-5 py-3 hover:bg-canvas">
                    <span class="grid size-8 flex-none place-items-center rounded-lg bg-naija-50 text-naija"><x-lucide-monitor-check class="size-4" aria-hidden="true" /></span>
                    <div class="min-w-0 flex-1 text-sm">
                        <div class="truncate font-medium">{{ $quiz->title }}</div>
                        <div class="text-muted">Open now · {{ $quiz->classSubject->classArm->displayName() }} · {{ $quiz->attempts_count }} done</div>
                    </div>
                </a>
            @empty
            @endforelse
            @forelse ($dueSoon->take(5) as $assignment)
                <a href="{{ route('school.classwork.assignments.show', $assignment) }}" class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-b-0 hover:bg-canvas">
                    <span class="grid size-8 flex-none place-items-center rounded-lg bg-brand-50 text-brand-600"><x-lucide-notebook-pen class="size-4" aria-hidden="true" /></span>
                    <div class="min-w-0 flex-1 text-sm">
                        <div class="truncate font-medium">{{ $assignment->title }}</div>
                        <div class="text-muted">Due {{ $assignment->due_at->format('D j M') }} · {{ $assignment->classSubject->classArm->displayName() }} · {{ $assignment->submissions_count }} in</div>
                    </div>
                </a>
            @empty
                @if ($openQuizzes->isEmpty())
                    <p class="m-0 px-5 py-4 text-sm text-muted">Nothing due this week.</p>
                @endif
            @endforelse
            <div class="flex flex-wrap gap-2 border-t border-line px-5 py-3">
                <x-button variant="soft" size="sm" icon="notebook-pen" :href="route('school.classwork.index', ['new' => 'assignment'])">New assignment</x-button>
                <x-button variant="soft" size="sm" icon="monitor-check" :href="route('school.classwork.index', ['new' => 'quiz'])">New quiz</x-button>
            </div>
        </x-card>

        @include('school.announcements._latest')

        @if ($myClasses->isNotEmpty() || $formClasses->isNotEmpty())
            @include('school.dashboard._my-classes')
        @endif

        @include('school.dashboard._term')
    </div>
</div>
