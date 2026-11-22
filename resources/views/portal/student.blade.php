<x-layouts.app :title="$ownProfile ? 'My class' : $student->fullName()">
    @php($arm = $student->currentEnrolment?->classArm)

    @unless ($ownProfile)
        <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
            <a href="{{ route('school.portal') }}" class="hover:text-ink">My children</a>
            <x-lucide-chevron-right class="size-4" aria-hidden="true" />
            <span class="text-ink">{{ $student->fullName() }}</span>
        </div>
    @endunless

    <div @class(['relative overflow-hidden rounded-3xl p-6 text-white sm:p-8', 'bg-brand-600' => $ownProfile, 'bg-ink' => ! $ownProfile]) data-enter>
        <div class="absolute -top-24 -right-24 size-72 rounded-full border-[48px] border-white/10" aria-hidden="true"></div>
        <div class="relative flex flex-wrap items-center gap-5">
            @if ($student->photo_path)
                <x-avatar :person="$student" class="size-20 rounded-3xl ring-4 ring-white/30" />
            @else
                <span class="grid size-20 flex-none place-items-center rounded-3xl bg-white text-2xl font-bold text-brand-600">{{ $student->initials() }}</span>
            @endif
            <div class="min-w-0 flex-1">
                <div class="text-sm font-medium text-brand-100">{{ $ownProfile ? 'Welcome back' : 'Your child at '.$currentSchool->name }}</div>
                <h1 class="m-0 text-[30px] leading-tight font-bold tracking-[-0.03em]">{{ $ownProfile ? $student->first_name : $student->fullName() }}</h1>
                <div class="mt-2 flex flex-wrap gap-2 text-sm">
                    @if ($arm)
                        <span class="rounded-full bg-white/15 px-3 py-1 font-semibold">{{ $arm->displayName() }}{{ $arm->stream ? ' · '.$arm->stream->name : '' }}</span>
                    @endif
                    <span class="rounded-full bg-white/15 px-3 py-1 font-mono">{{ $student->admission_no }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3" data-stagger>
        @php($todo = $assignments->reject(fn ($assignment) => $submissions->has($assignment->id) || $assignment->isPastDue())->count())
        @php($openQuizzes = $quizzes->filter(fn ($quiz) => $quiz->isOpen() && ! $quizAttempts->get($quiz->id)?->submitted_at)->count())
        @if ($ownProfile)
            <x-stat-card label="To hand in" :value="(string) $todo" icon="notebook-pen" tone="gold" :hint="$todo ? 'Assignments due soon' : 'All caught up'" :href="route('school.portal.classwork').'#todo'" />
            <x-stat-card label="Quizzes open" :value="(string) $openQuizzes" icon="monitor-check" :hint="$openQuizzes ? 'Take them in My work' : 'None right now'" :href="route('school.portal.classwork')" />
            <x-stat-card label="Attendance this term" :value="$attendance && $attendance['rate'] !== null ? $attendance['rate'].'%' : '—'" icon="clipboard-check" tone="naija" :hint="$attendance ? $attendance['absent'].' days absent' : null" />
        @else
            <x-stat-card label="Attendance this term" :value="$attendance && $attendance['rate'] !== null ? $attendance['rate'].'%' : '—'" icon="clipboard-check" tone="naija" :hint="$attendance ? $attendance['absent'].' absent · '.$attendance['late'].' late' : null" />
            <x-stat-card label="School fees" :value="$balance > 0 ? \App\Support\Money::format($balance) : 'Paid up'" icon="receipt" :tone="$balance > 0 ? 'gold' : 'naija'" :hint="$balance > 0 ? 'Outstanding' : null" />
            <x-stat-card label="Homework" :value="$submissions->count().' / '.$assignments->count()" icon="notebook-pen" tone="sky" hint="Handed in this term" />
        @endif
    </div>

    @if ($ownProfile && $todayLessons->isNotEmpty())
        @include('portal._today')
    @endif

    @if ($ownProfile)
        <div class="max-w-5xl">@include('school.announcements._latest')</div>
    @endif

    <x-tabs :tabs="$ownProfile
        ? ['results' => ['label' => 'Results', 'count' => $publishedResults->count()], 'work' => ['label' => 'My work', 'count' => $todo + $openQuizzes], 'timetable' => 'Timetable', 'exams' => ['label' => 'Exams', 'count' => $examPapers->count()], 'attendance' => 'Attendance', 'subjects' => 'Subjects & teachers', 'calendar' => 'Term calendar']
        : ['results' => ['label' => 'Results', 'count' => $publishedResults->count()], 'fees' => ['label' => 'Fees', 'count' => $invoices->filter(fn ($invoice) => $invoice->status->isOpen())->count()], 'attendance' => 'Attendance', 'work' => 'Homework & tests', 'timetable' => 'Timetable', 'exams' => ['label' => 'Exams', 'count' => $examPapers->count()], 'subjects' => 'Teachers', 'calendar' => 'Term calendar', 'contacts' => 'Contacts']" label="Class information" class="max-w-5xl">


    <x-tab name="exams">
        @if ($examPapers->isEmpty())
            <x-card><x-empty-state icon="calendar-clock" title="No exam timetable yet" body="When the school sets this term’s exams, the papers show here." /></x-card>
        @else
            <x-card :padding="false">
                @foreach ($examPapers as $paper)
                    <div @class(['flex flex-wrap items-center gap-3 border-b border-line px-5 py-3 last:border-b-0', 'opacity-60' => $paper->endsAt()->isPast()])>
                        <div class="w-28 flex-none text-center">
                            <div class="text-xs font-semibold text-brand-600 uppercase">{{ $paper->starts_at->format('D j M') }}</div>
                            <div class="text-sm text-muted">{{ $paper->times() }}</div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold">{{ $paper->subject->name }}</div>
                            <div class="text-sm text-muted">{{ collect([$paper->venue, $paper->notes])->filter()->join(' · ') ?: 'Venue to be announced' }}</div>
                        </div>
                        @if ($paper->starts_at->isToday())<x-badge tone="gold">Today</x-badge>@elseif ($paper->endsAt()->isPast())<x-badge>Done</x-badge>@endif
                    </div>
                @endforeach
            </x-card>
        @endif
    </x-tab>

    <x-tab name="timetable">
        <x-card :padding="false" class="p-2">
            @if ($timetable)
                @include('school.timetable._week', ['periods' => $periods, 'grid' => $timetable, 'show' => 'class'])
            @else
                <x-empty-state icon="calendar-days" title="No timetable yet" body="The school hasn’t published this class’s timetable yet." />
            @endif
        </x-card>
    </x-tab>

    <x-tab name="results">
        @if ($publishedResults->isEmpty())
            <x-card><x-empty-state icon="file-text" title="No results yet" body="Results appear here as soon as the school publishes them for your class." /></x-card>
        @else
            <div class="flex flex-col gap-3">
                @foreach ($publishedResults as $result)
                    <div class="flex flex-wrap items-center gap-4 rounded-2xl border border-line bg-white p-5">
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold">{{ $result['term']->name }} · {{ $result['term']->academicSession->name }}</div>
                            <div class="text-sm text-muted">{{ $result['arm']->displayName() }} · {{ $result['row']['subject_count'] }} subjects</div>
                        </div>
                        @if ($result['row']['complete'])
                            <div class="text-center"><div class="text-xs text-muted">Average</div><div class="text-xl font-bold">{{ $result['row']['average'] }}</div></div>
                            <div class="text-center"><div class="text-xs text-muted">Position</div><div class="text-xl font-bold text-brand-600">{{ isset($result['row']['position']) ? \App\Support\Results\ResultCalculator::ordinal($result['row']['position']) : '—' }} <span class="text-sm font-medium text-muted">of {{ $result['size'] }}</span></div></div>
                        @else
                            <x-badge tone="gold">Results so far</x-badge>
                            <div class="text-center"><div class="text-xs text-muted">Average so far</div><div class="text-xl font-bold">{{ $result['row']['percent'] }}%</div></div>
                        @endif
                        <x-button data-native variant="soft" icon="file-text" :href="route('school.results.report', [$student, 'term' => $result['term']->id])">{{ $result['row']['complete'] ? 'Report card' : 'Results so far' }}</x-button>
                    </div>
                @endforeach
            </div>
        @endif
    </x-tab>

    @unless ($ownProfile)
        <x-tab name="fees">
            @include('portal._fees')
        </x-tab>
    @endunless



    <x-tab name="attendance">
        @include('portal._attendance')
    </x-tab>

    <x-tab name="work">
        @include('portal._classwork')
    </x-tab>

    <x-tab name="subjects">
        <x-card :padding="false">
            <h2 class="m-0 border-b border-line px-6 py-4 text-lg font-semibold">Subjects and teachers</h2>
            @if ($arm && $arm->classSubjects->isNotEmpty())
                <ul class="m-0 list-none p-0">
                    @foreach ($arm->classSubjects->sortBy('subject.name') as $classSubject)
                        <li class="flex items-center justify-between gap-3 border-b border-line px-6 py-3 text-[15px] last:border-b-0">
                            <span class="flex items-center gap-3">
                                <span class="grid size-8 place-items-center rounded-lg bg-brand-50 font-mono text-[11px] font-bold text-brand-600">{{ $classSubject->subject->code }}</span>
                                <span class="font-medium">{{ $classSubject->subject->name }}</span>
                            </span>
                            <span class="truncate text-sm text-muted">{{ $classSubject->teacher?->fullName() ?? '—' }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-empty-state icon="book-open" title="No subjects yet" />
            @endif
        </x-card>
    </x-tab>

    <x-tab name="calendar" class="flex flex-col gap-6">
            @if ($term)
                <x-card>
                    <div class="kicker">School calendar</div>
                    <h2 class="m-0 mt-1 text-lg font-semibold">{{ $term->name }} · {{ $term->academicSession->name }}</h2>
                    <dl class="m-0 mt-4 grid grid-cols-[auto_minmax(0,1fr)] gap-x-6 gap-y-2.5 text-[15px]">
                        <dt class="text-muted">Resumed</dt>
                        <dd class="m-0 font-medium">{{ $term->starts_on->format('D j M Y') }}</dd>
                        @if ($term->mid_term_starts_on)
                            <dt class="text-muted">Mid-term break</dt>
                            <dd class="m-0 font-medium">{{ $term->mid_term_starts_on->format('j M') }} – {{ $term->mid_term_ends_on?->format('j M') }}</dd>
                        @endif
                        <dt class="text-muted">Vacation</dt>
                        <dd class="m-0 font-medium">{{ $term->ends_on->format('D j M Y') }}</dd>
                        @if ($term->next_term_begins_on)
                            <dt class="text-muted">Next term</dt>
                            <dd class="m-0 font-medium">{{ $term->next_term_begins_on->format('D j M Y') }}</dd>
                        @endif
                    </dl>
                </x-card>
            @endif

            @unless ($term)
                <x-empty-state icon="calendar-range" title="No term on the calendar yet" body="When the school sets the current term, the dates will show here." />
            @endunless

    </x-tab>

    <x-tab name="contacts">
            @unless ($ownProfile)
                <x-card>
                    <h2 class="m-0 text-lg font-semibold">Contacts on record</h2>
                    <ul class="m-0 mt-3 flex list-none flex-col gap-2 p-0 text-[15px]">
                        @foreach ($student->guardians as $guardian)
                            <li class="flex items-center justify-between gap-2">
                                <span>{{ $guardian->fullName() }} <span class="text-muted">· {{ $guardian->relationship->label() }}</span></span>
                                <span class="font-mono text-sm">{{ $guardian->phone }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="m-0 mt-3 text-sm text-muted">Something wrong? Let the school office know.</p>
                </x-card>
            @endunless
    </x-tab>
</x-tabs>
</x-layouts.app>
