{{-- Admin and principal: today's registers, money, results, admissions, then the rest. --}}
<div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
    <div class="flex min-w-0 flex-col gap-6">
        @if ($today)
            <x-card :padding="false">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <div>
                        <h2 class="m-0 text-base font-semibold">Today’s registers</h2>
                        <p class="m-0 text-sm text-muted">
                            @if ($today['open'])
                                {{ $today['taken'] }} of {{ $today['classes'] }} classes · {{ $today['present'] + $today['late'] }} in, {{ $today['absent'] }} absent{{ $today['late'] ? ', '.$today['late'].' late' : '' }}
                            @else
                                No school today
                            @endif
                        </p>
                    </div>
                    <a href="{{ route('school.attendance.index') }}" class="text-sm font-semibold text-brand-600 hover:text-ink">Attendance</a>
                </div>
                <div class="flex flex-col gap-5 p-5">
                    @if ($today['open'] && $arms->isNotEmpty())
                        <ul class="m-0 flex list-none flex-wrap gap-2 p-0" aria-label="Register taken, by class">
                            @foreach ($arms as $arm)
                                @php($taken = isset($today['byClass'][$arm->id]))
                                <li>
                                    <a href="{{ route('school.attendance.register', $arm) }}" @class([
                                        'inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-sm font-medium transition',
                                        'border-naija/20 bg-naija-50 text-naija-700 hover:border-naija' => $taken,
                                        'border-gold-200 bg-gold-50 text-gold hover:border-gold' => ! $taken,
                                    ]) title="{{ $taken ? 'Register taken' : 'Not taken yet' }}">
                                        <x-dynamic-component :component="'lucide-'.($taken ? 'check' : 'clock')" class="size-3.5" aria-hidden="true" />
                                        {{ $arm->displayName() }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($trend->isNotEmpty())
                        <div>
                            <h3 class="m-0 text-sm font-semibold text-muted">In school, last {{ $trend->count() }} school days</h3>
                            <div class="mt-3 grid h-28 items-end gap-2" style="grid-template-columns: repeat({{ $trend->count() }}, minmax(0, 1fr))" role="img" aria-label="Share of students in school on each recent school day">
                                @foreach ($trend as $day)
                                    <div class="flex h-full flex-col items-center justify-end gap-1" title="{{ $day['date']->format('D j M') }}: {{ $day['rate'] }}%">
                                        <span class="text-[10px] font-semibold text-muted">{{ $day['rate'] }}%</span>
                                        <span @class(['w-full rounded-t-md', 'bg-naija' => $day['rate'] >= 90, 'bg-gold' => $day['rate'] < 90]) style="height: {{ max(4, $day['rate'] - 50) * 2 }}%"></span>
                                        <span class="text-[10px] text-muted">{{ $day['date']->format('D') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </x-card>
        @endif

        @if ($fees)
            @include('school.dashboard._fees')
        @endif

        @if ($results)
            <x-card :padding="false">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <div>
                        <h2 class="m-0 text-base font-semibold">Results this term</h2>
                        <p class="m-0 text-sm text-muted">{{ $results['scored'] }} of {{ $results['subjects'] }} class subjects have scores · {{ $results['published'] }} of {{ $results['classes'] }} classes published</p>
                    </div>
                    <a href="{{ route('school.results.index') }}" class="text-sm font-semibold text-brand-600 hover:text-ink">Broadsheets</a>
                </div>
                <div class="flex flex-col gap-4 p-5">
                    <div class="grid grid-cols-2 gap-4">
                        @foreach (['Scores entered' => [$results['rate'] ?? 0, 'bg-sky'], 'Classes published' => [$results['classes'] ? (int) round($results['published'] / $results['classes'] * 100) : 0, 'bg-naija']] as $label => [$rate, $colour])
                            <div>
                                <div class="flex justify-between text-sm"><span class="font-semibold">{{ $label }}</span><span class="text-muted">{{ $rate }}%</span></div>
                                <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-chip"><div class="h-full rounded-full {{ $colour }}" style="width: {{ $rate }}%"></div></div>
                            </div>
                        @endforeach
                    </div>
                    @if ($results['unscored']->isNotEmpty())
                        <details class="group">
                            <summary class="cursor-pointer text-sm font-semibold text-brand-600">{{ $results['unscored']->count() }} class subjects without scores</summary>
                            <ul class="m-0 mt-2 grid list-none grid-cols-1 gap-1 p-0 text-sm sm:grid-cols-2">
                                @foreach ($results['unscored']->take(20) as $classSubject)
                                    <li class="flex justify-between gap-2 rounded-lg bg-canvas px-3 py-1.5"><span>{{ $classSubject->subject->name }} · {{ $classSubject->classArm->displayName() }}</span><span class="truncate text-muted">{{ $classSubject->teacher?->fullName() ?? 'No teacher' }}</span></li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </div>
            </x-card>
        @endif

        @if ($myClasses->isNotEmpty() || $formClasses->isNotEmpty())
            @include('school.dashboard._my-classes')
        @endif
    </div>

    <div class="flex min-w-0 flex-col gap-6">
        @include('school.announcements._latest')
        @include('school.dashboard._term')

        @if ($admissions !== null)
            <x-card>
                <div class="flex items-center justify-between">
                    <h2 class="m-0 text-base font-semibold">Admissions</h2>
                    <a href="{{ route('school.admissions.index') }}" class="text-sm font-semibold text-brand-600 hover:text-ink">Open</a>
                </div>
                <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                    @foreach (['new' => 'New', 'exam' => 'Exam', 'offered' => 'Offered'] as $stage => $label)
                        <a href="{{ route('school.admissions.index') }}#{{ $stage }}" class="rounded-xl bg-canvas px-2 py-3 hover:bg-brand-50">
                            <div class="text-2xl font-bold">{{ $admissions[$stage] }}</div>
                            <div class="text-xs text-muted">{{ $label }}</div>
                        </a>
                    @endforeach
                </div>
            </x-card>
        @endif

        @include('school.dashboard._cashflow')
        @include('school.dashboard._payments')

        <x-card>
            <h2 class="m-0 text-base font-semibold">School at a glance</h2>
            <dl class="m-0 mt-3 grid grid-cols-2 gap-3 text-center">
                @foreach ([
                    'Girls' => number_format($genderSplit['female'] ?? 0),
                    'Boys' => number_format($genderSplit['male'] ?? 0),
                    'Classes' => number_format($arms->count()),
                    'Subjects' => number_format($subjectCount),
                    'Parents' => number_format($guardianCount),
                    'People who sign in' => number_format($memberCount),
                ] as $label => $value)
                    <div class="rounded-xl bg-canvas px-3 py-2.5"><dt class="text-xs text-muted">{{ $label }}</dt><dd class="m-0 text-lg font-bold">{{ $value }}</dd></div>
                @endforeach
            </dl>
        </x-card>

        @include('school.dashboard._activity')
    </div>
</div>
