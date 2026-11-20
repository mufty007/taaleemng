<x-layouts.app title="Dashboard">
    @php
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $firstName = \Illuminate\Support\Str::before(auth()->user()->name, ' ');
        $doneCount = collect($setupSteps)->where('done', true)->count();
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

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
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
            <ol class="m-0 list-none p-0" data-stagger>
                @foreach ($setupSteps as $step)
                    <li class="flex items-start gap-4 border-b border-line px-6 py-4 last:border-b-0">
                        @if ($step['done'])
                            <span class="grid size-10 flex-none place-items-center rounded-xl bg-naija-50 text-naija">
                                <x-lucide-check class="size-5" stroke-width="2.5" aria-hidden="true" />
                            </span>
                        @else
                            <span class="grid size-10 flex-none place-items-center rounded-xl bg-brand-50 text-brand-600">
                                <x-dynamic-component :component="'lucide-'.$step['icon']" class="size-5" aria-hidden="true" />
                            </span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div @class(['font-semibold', 'text-muted line-through decoration-1' => $step['done']])>{{ $step['title'] }}</div>
                            <div class="text-sm text-muted">{{ $step['body'] }}</div>
                        </div>
                        @if ($step['done'])
                            <span class="sr-only">Done</span>
                        @else
                            <x-badge>Coming soon</x-badge>
                        @endif
                    </li>
                @endforeach
            </ol>
        </x-card>

        <div class="flex flex-col gap-6">
            <div class="relative overflow-hidden rounded-2xl bg-ink p-6 text-white">
                <div class="absolute -top-16 -right-16 size-48 rounded-full border-[28px] border-white/5" aria-hidden="true"></div>
                <div class="relative flex flex-col gap-3">
                    <div class="kicker text-brand-200">We set it up for you</div>
                    <p class="m-0 text-xl leading-snug font-semibold">Send us your student list, classes and fee schedule, and we’ll import them before resumption.</p>
                    <a href="mailto:{{ config('taaleem.marketing.contact_email') }}" class="mt-1 inline-flex items-center gap-2 self-start rounded-xl bg-brand-600 px-4 py-2.5 text-[15px] font-semibold text-white transition hover:bg-brand-700">
                        <x-lucide-mail class="size-4" aria-hidden="true" /> Email our team
                    </a>
                </div>
            </div>

            <x-card>
                <h2 class="m-0 mb-4 text-lg font-semibold">School details</h2>
                <dl class="m-0 grid grid-cols-[auto_minmax(0,1fr)] gap-x-6 gap-y-3 text-[15px]">
                    <dt class="text-muted">Type</dt>
                    <dd class="m-0 font-medium">{{ $school->school_type->label() }}</dd>
                    <dt class="text-muted">School code</dt>
                    <dd class="m-0 font-medium">{{ $school->short_code }}</dd>
                    <dt class="text-muted">State</dt>
                    <dd class="m-0 font-medium">{{ $school->state ?? '—' }}</dd>
                    <dt class="text-muted">Phone</dt>
                    <dd class="m-0 font-medium">{{ $school->phone ?? '—' }}</dd>
                    <dt class="text-muted">Email</dt>
                    <dd class="m-0 truncate font-medium">{{ $school->email ?? '—' }}</dd>
                </dl>
            </x-card>
        </div>
    </div>
</x-layouts.app>
