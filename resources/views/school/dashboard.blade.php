<x-layouts.app title="Dashboard">
    @php
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $firstName = \Illuminate\Support\Str::before(auth()->user()->name, ' ');
        $doneCount = collect($setupSteps)->where('done', true)->count();
        $setupComplete = $doneCount === count($setupSteps);
        $maxClassSize = max(1, $classSizes->max('count') ?? 1);
    @endphp

    <x-page-header :kicker="$school->name" :title="$greeting.', '.$firstName" :description="'Signed in as '.$roles->join(', ', ' and ').'.'">
        <x-slot:actions>
            @if ($school->status === \App\Enums\SchoolStatus::Trial && $school->trial_ends_at)
                <x-badge tone="gold">Trial · {{ (int) max(0, now()->diffInDays($school->trial_ends_at, false)) }} days left</x-badge>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" data-stagger>
        @foreach ($stats as $stat)
            <x-stat-card :label="$stat['label']" :value="$stat['value']" :hint="$stat['hint']" :icon="$stat['icon']" :tone="$stat['tone']" />
        @endforeach
    </div>

    {{-- Teacher view: my classes --}}
    @if ($myClasses->isNotEmpty() || $formClasses->isNotEmpty())
        <x-card :padding="false">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-4">
                <div>
                    <h2 class="m-0 text-lg font-semibold">My classes</h2>
                    <p class="m-0 text-sm text-muted">The classes you teach this session.</p>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($formClasses as $formClass)
                    <a href="{{ route('school.classes.show', $formClass) }}" class="flex flex-col gap-2 rounded-2xl border border-brand-200 bg-brand-50 p-4 transition hover:border-brand">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-lg font-bold">{{ $formClass->displayName() }}</span>
                            <x-badge tone="brand">Form teacher</x-badge>
                        </div>
                        <span class="text-sm text-muted">{{ $formClass->enrolments_count }} students</span>
                    </a>
                @endforeach
                @foreach ($myClasses as $row)
                    <a href="{{ route('school.classes.show', $row['arm']) }}" class="flex flex-col gap-2 rounded-2xl border border-line p-4 transition hover:border-brand">
                        <span class="text-lg font-bold">{{ $row['arm']->displayName() }}</span>
                        <span class="text-sm text-muted">{{ implode(', ', $row['subjects']) }}</span>
                    </a>
                @endforeach
            </div>
        </x-card>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
        @if (! $setupComplete && auth()->user()->can('school.manage'))
            <x-card :padding="false">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-5">
                    <div>
                        <h2 class="m-0 text-lg font-semibold">Get your school ready</h2>
                        <p class="m-0 text-sm text-muted">{{ $doneCount }} of {{ count($setupSteps) }} done. Most schools finish in a day.</p>
                    </div>
                    <div class="h-2 w-40 overflow-hidden rounded-full bg-chip" role="progressbar" aria-valuenow="{{ $doneCount }}" aria-valuemin="0" aria-valuemax="{{ count($setupSteps) }}" aria-label="Setup progress">
                        <div class="h-full rounded-full bg-naija" style="width: {{ round($doneCount / count($setupSteps) * 100) }}%"></div>
                    </div>
                </div>
                <ol class="m-0 list-none p-0">
                    @foreach ($setupSteps as $step)
                        <li class="border-b border-line last:border-b-0">
                            <a href="{{ route($step['route']) }}" class="flex items-start gap-4 px-6 py-4 hover:bg-canvas">
                                <span @class(['grid size-10 flex-none place-items-center rounded-xl', 'bg-naija-50 text-naija' => $step['done'], 'bg-brand-50 text-brand-600' => ! $step['done']])>
                                    @if ($step['done'])
                                        <x-lucide-check class="size-5" stroke-width="2.5" aria-hidden="true" />
                                    @else
                                        <x-dynamic-component :component="'lucide-'.$step['icon']" class="size-5" aria-hidden="true" />
                                    @endif
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span @class(['block font-semibold', 'text-muted line-through decoration-1' => $step['done']])>{{ $step['title'] }}</span>
                                    <span class="block text-sm text-muted">{{ $step['body'] }}</span>
                                </span>
                                <x-lucide-chevron-right class="mt-2 size-4 text-muted" aria-hidden="true" />
                            </a>
                        </li>
                    @endforeach
                </ol>
            </x-card>
        @else
            <x-card :padding="false">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <div>
                        <h2 class="m-0 text-lg font-semibold">Students per class</h2>
                        <p class="m-0 text-sm text-muted">
                            {{ number_format($genderSplit['female'] ?? 0) }} girls · {{ number_format($genderSplit['male'] ?? 0) }} boys
                        </p>
                    </div>
                    @can('students.view-all')
                        <a href="{{ route('school.classes.index') }}" class="text-sm font-semibold text-brand-600 hover:text-ink">All classes</a>
                    @endcan
                </div>
                @if ($classSizes->isEmpty())
                    <x-empty-state icon="layers" title="No classes yet" body="Set up the session and classes to see class sizes here." />
                @else
                    <ul class="m-0 grid list-none grid-cols-1 gap-x-8 gap-y-2.5 p-6 sm:grid-cols-2">
                        @foreach ($classSizes as $class)
                            <li class="grid grid-cols-[72px_minmax(0,1fr)_28px] items-center gap-3 text-sm">
                                <span class="font-medium">{{ $class['name'] }}</span>
                                <span class="h-2.5 overflow-hidden rounded-full bg-chip" aria-hidden="true">
                                    <span class="block h-full rounded-full bg-brand" style="width: {{ round($class['count'] / $maxClassSize * 100) }}%"></span>
                                </span>
                                <span class="text-right font-semibold">{{ $class['count'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        @endif

        <div class="flex flex-col gap-6">
            @if ($term)
                <x-card>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="kicker">This term</div>
                            <h2 class="m-0 mt-1 text-lg font-semibold">{{ $term->name }} · {{ $term->academicSession->name }}</h2>
                        </div>
                        @if ($week = $term->weekOf(now()))
                            <x-badge tone="brand">Week {{ $week }}</x-badge>
                        @endif
                    </div>
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
                            <dt class="text-muted">Next term begins</dt>
                            <dd class="m-0 font-medium">{{ $term->next_term_begins_on->format('D j M Y') }}</dd>
                        @endif
                    </dl>
                </x-card>
            @endif

            @if ($recentActivity->isNotEmpty())
                <x-card :padding="false">
                    <div class="flex items-center justify-between border-b border-line px-6 py-4">
                        <h2 class="m-0 text-lg font-semibold">Recent activity</h2>
                        <a href="{{ route('school.activity.index') }}" class="text-sm font-semibold text-brand-600 hover:text-ink">View all</a>
                    </div>
                    <ul class="m-0 list-none p-0">
                        @foreach ($recentActivity as $activity)
                            <li class="flex items-start gap-3 border-b border-line px-6 py-3 last:border-b-0">
                                <span class="mt-0.5 grid size-8 flex-none place-items-center rounded-full bg-chip text-[11px] font-semibold">{{ $activity->causer?->initials() ?? '·' }}</span>
                                <div class="min-w-0 flex-1 text-sm">
                                    <span class="font-semibold">{{ $activity->causer?->name ?? 'System' }}</span>
                                    <span class="text-muted">{{ $activity->description }}</span>
                                    <div class="text-xs text-muted">{{ $activity->created_at->diffForHumans() }}</div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            <x-card>
                <h2 class="m-0 mb-4 text-lg font-semibold">School details</h2>
                <dl class="m-0 grid grid-cols-[auto_minmax(0,1fr)] gap-x-6 gap-y-3 text-[15px]">
                    <dt class="text-muted">Type</dt>
                    <dd class="m-0 font-medium">{{ $school->school_type->label() }}</dd>
                    <dt class="text-muted">School code</dt>
                    <dd class="m-0 font-medium">{{ $school->short_code }} <span class="text-sm text-muted">(students use this to log in)</span></dd>
                    <dt class="text-muted">State</dt>
                    <dd class="m-0 font-medium">{{ $school->state ?? '—' }}</dd>
                    <dt class="text-muted">Phone</dt>
                    <dd class="m-0 font-medium">{{ $school->phone ?? '—' }}</dd>
                </dl>
            </x-card>
        </div>
    </div>
</x-layouts.app>
