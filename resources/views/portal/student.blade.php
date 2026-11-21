<x-layouts.app :title="$ownProfile ? 'My class' : $student->fullName()">
    @php($arm = $student->currentEnrolment?->classArm)

    @unless ($ownProfile)
        <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
            <a href="{{ route('school.portal') }}" class="hover:text-ink">My children</a>
            <x-lucide-chevron-right class="size-4" aria-hidden="true" />
            <span class="text-ink">{{ $student->fullName() }}</span>
        </div>
    @endunless

    <div class="relative overflow-hidden rounded-3xl bg-brand-600 p-6 text-white sm:p-8" data-enter>
        <div class="absolute -top-24 -right-24 size-72 rounded-full border-[48px] border-white/10" aria-hidden="true"></div>
        <div class="relative flex flex-wrap items-center gap-5">
            <span class="grid size-20 flex-none place-items-center rounded-3xl bg-white text-2xl font-bold text-brand-600">{{ $student->initials() }}</span>
            <div class="min-w-0 flex-1">
                <div class="text-sm font-medium text-brand-100">{{ $ownProfile ? 'Welcome back' : $currentSchool->name }}</div>
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
        <x-stat-card label="Form teacher" :value="$arm?->formTeacher?->fullName() ?? '—'" icon="user-round" class="[&_div.text-3xl]:text-xl" />
        <x-stat-card label="Subjects" :value="(string) ($arm?->classSubjects->count() ?? 0)" icon="book-open" tone="sky" />
        <x-stat-card label="Students in class" :value="(string) $classmates" icon="users" tone="naija" />
    </div>

    <x-tabs :tabs="array_filter(['results' => ['label' => 'Results', 'count' => $publishedResults->count()], 'fees' => ['label' => 'Fees', 'count' => $invoices->filter(fn ($invoice) => $invoice->status->isOpen())->count()], 'subjects' => 'Subjects & teachers', 'calendar' => 'Term calendar', 'contacts' => $ownProfile ? null : 'Contacts'])" label="Class information" class="max-w-4xl">
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
                        <div class="text-center"><div class="text-xs text-muted">Average</div><div class="text-xl font-bold">{{ $result['row']['average'] }}</div></div>
                        <div class="text-center"><div class="text-xs text-muted">Position</div><div class="text-xl font-bold text-brand-600">{{ isset($result['row']['position']) ? \App\Support\Results\ResultCalculator::ordinal($result['row']['position']) : '—' }} <span class="text-sm font-medium text-muted">of {{ $result['size'] }}</span></div></div>
                        <x-button variant="soft" icon="file-text" :href="route('school.results.report', [$student, 'term' => $result['term']->id])">Report card</x-button>
                    </div>
                @endforeach
            </div>
        @endif
    </x-tab>

    <x-tab name="fees">
        @include('portal._fees')
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

            <x-card>
                <h2 class="m-0 text-lg font-semibold">Coming soon</h2>
                <ul class="m-0 mt-3 flex list-none flex-col gap-2.5 p-0 text-[15px] text-muted">
                    <li class="flex items-center gap-2.5"><x-lucide-monitor-check class="size-4 text-brand-600" aria-hidden="true" /> CBT exams and practice tests</li>
                    <li class="flex items-center gap-2.5"><x-lucide-receipt class="size-4 text-brand-600" aria-hidden="true" /> School fees and receipts</li>
                    <li class="flex items-center gap-2.5"><x-lucide-calendar-days class="size-4 text-brand-600" aria-hidden="true" /> Class timetable</li>
                </ul>
            </x-card>

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
