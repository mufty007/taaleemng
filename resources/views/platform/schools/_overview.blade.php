@php
    $done = collect($setupSteps)->where('done', true)->count();
@endphp

<div class="flex flex-col gap-6">
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        @foreach ($stats as $stat)
            <x-stat-card :label="$stat['label']" :value="$stat['value']" :icon="$stat['icon']" :tone="$stat['tone']" />
        @endforeach
    </div>

    <x-card>
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="m-0 text-base font-semibold">Activity, last 30 days</h2>
            <span class="text-sm text-muted">{{ number_format($usage['week']) }} people active this week · {{ number_format($usage['month']) }} this month</span>
        </div>
        <x-report.columns class="mt-5" tone="naija" label="Actions in the school each day" :bars="$usage['daily']" />
    </x-card>

    <x-card :padding="false">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
            <div>
                <h2 class="m-0 text-base font-semibold">Getting set up</h2>
                <p class="m-0 text-sm text-muted">{{ $done }} of {{ count($setupSteps) }} done{{ $done === count($setupSteps) ? '. This school is fully set up.' : '. Help them with the next step.' }}</p>
            </div>
            <div class="h-2 w-32 overflow-hidden rounded-full bg-chip" role="progressbar" aria-valuenow="{{ $done }}" aria-valuemin="0" aria-valuemax="{{ count($setupSteps) }}" aria-label="Setup progress">
                <div class="h-full rounded-full bg-naija" style="width: {{ round($done / max(1, count($setupSteps)) * 100) }}%"></div>
            </div>
        </div>
        <ol class="m-0 list-none p-0">
            @foreach ($setupSteps as $step)
                <li class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-b-0">
                    <span @class(['grid size-8 flex-none place-items-center rounded-lg', 'bg-naija-50 text-naija' => $step['done'], 'bg-chip text-muted' => ! $step['done']])>
                        <x-dynamic-component :component="'lucide-'.($step['done'] ? 'check' : $step['icon'])" class="size-4" aria-hidden="true" />
                    </span>
                    <span @class(['min-w-0 flex-1 text-sm', 'text-muted' => $step['done'], 'font-semibold' => ! $step['done']])>{{ $step['title'] }}</span>
                    @unless ($step['done'])
                        <a href="{{ route($step['route'], $school) }}" class="text-sm font-semibold text-brand-600 hover:text-ink">Open</a>
                    @endunless
                </li>
            @endforeach
        </ol>
    </x-card>
</div>
