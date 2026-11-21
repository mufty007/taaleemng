<x-layouts.app title="Dashboard">
    @php
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $firstName = \Illuminate\Support\Str::before(auth()->user()->name, ' ');
        $doneCount = collect($setupSteps)->where('done', true)->count();
        $setupComplete = $doneCount === count($setupSteps);
        $nextStep = collect($setupSteps)->firstWhere('done', false);
    @endphp

    <x-page-header :kicker="$school->name" :title="$greeting.', '.$firstName" :description="now()->format('l j F Y').($term && ($week = $term->weekOf(now())) ? ' · '.$term->name.', week '.$week : '').' · '.$roles->join(', ', ' and ')">
        <x-slot:actions>
            @if ($school->status === \App\Enums\SchoolStatus::Trial && $school->trial_ends_at)
                <x-badge tone="gold">Trial · {{ (int) max(0, now()->diffInDays($school->trial_ends_at, false)) }} days left</x-badge>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Setup, while it is unfinished: one line and the next step, the full list on demand. --}}
    @if (! $setupComplete && auth()->user()->can('school.manage'))
        <div x-data="{ open: false }" class="rounded-2xl border border-brand-200 bg-brand-50/60">
            <div class="flex flex-wrap items-center gap-4 px-5 py-4">
                <div class="min-w-0 flex-1">
                    <h2 class="m-0 text-base font-semibold">Get your school ready · {{ $doneCount }} of {{ count($setupSteps) }} done</h2>
                    <p class="m-0 text-sm text-muted">Next: {{ $nextStep['title'] }}. {{ $nextStep['body'] }}</p>
                </div>
                <div class="h-2 w-32 overflow-hidden rounded-full bg-white" role="progressbar" aria-valuenow="{{ $doneCount }}" aria-valuemin="0" aria-valuemax="{{ count($setupSteps) }}" aria-label="Setup progress">
                    <div class="h-full rounded-full bg-naija" style="width: {{ round($doneCount / count($setupSteps) * 100) }}%"></div>
                </div>
                <x-button variant="brand" size="sm" icon="arrow-right" :href="route($nextStep['route'])">Continue</x-button>
                <button type="button" class="text-sm font-semibold text-brand-600 hover:text-ink" x-on:click="open = ! open" :aria-expanded="open.toString()">All steps</button>
            </div>
            <ol x-show="open" x-collapse x-cloak class="m-0 grid list-none grid-cols-1 gap-2 border-t border-brand-200 p-4 sm:grid-cols-2 xl:grid-cols-5">
                @foreach ($setupSteps as $step)
                    <li>
                        <a href="{{ route($step['route']) }}" class="flex h-full items-start gap-3 rounded-xl bg-white p-3 hover:shadow-card">
                            <span @class(['grid size-8 flex-none place-items-center rounded-lg', 'bg-naija-50 text-naija' => $step['done'], 'bg-brand-50 text-brand-600' => ! $step['done']])>
                                <x-dynamic-component :component="'lucide-'.($step['done'] ? 'check' : $step['icon'])" class="size-4" aria-hidden="true" />
                            </span>
                            <span @class(['text-sm font-semibold', 'text-muted line-through decoration-1' => $step['done']])>{{ $step['title'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    {{-- What needs doing first. --}}
    @if ($attention)
        <section aria-labelledby="attention-title" class="flex flex-col gap-3">
            <h2 id="attention-title" class="m-0 flex items-center gap-2 text-sm font-semibold tracking-wide text-muted uppercase"><x-lucide-zap class="size-4 text-gold" aria-hidden="true" /> Needs your attention</h2>
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2" data-stagger>
                @foreach ($attention as $item)
                    @php
                        $tone = match ($item['tone']) {
                            'gold' => 'bg-gold-50 text-gold',
                            'danger' => 'bg-danger-50 text-danger',
                            'sky' => 'bg-sky-50 text-sky',
                            'neutral' => 'bg-chip text-muted',
                            default => 'bg-brand-50 text-brand-600',
                        };
                    @endphp
                    <a href="{{ $item['url'] }}" class="group flex items-start gap-4 rounded-2xl border border-line bg-white p-4 transition hover:border-brand hover:shadow-card">
                        <span class="grid size-10 flex-none place-items-center rounded-xl {{ $tone }}"><x-dynamic-component :component="'lucide-'.$item['icon']" class="size-5" aria-hidden="true" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold">{{ $item['title'] }}</span>
                            <span class="block truncate text-sm text-muted">{{ $item['body'] }}</span>
                        </span>
                        <x-lucide-arrow-right class="mt-2.5 size-4 flex-none text-muted transition group-hover:translate-x-0.5 group-hover:text-brand-600" aria-hidden="true" />
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" data-stagger>
        @foreach ($stats as $stat)
            <x-stat-card :label="$stat['label']" :value="$stat['value']" :hint="$stat['hint']" :icon="$stat['icon']" :tone="$stat['tone']" :href="$stat['url'] ?? null" />
        @endforeach
    </div>

    @if ($mode === 'teacher')
        @include('school.dashboard._teacher')
    @elseif ($mode === 'bursar')
        @include('school.dashboard._bursar')
    @else
        @include('school.dashboard._office')
    @endif
</x-layouts.app>
