<x-layouts.app title="Platform">
    <x-page-header kicker="Platform console" title="Overview" :description="now()->format('l j F Y').' · every school on Ta’aleem at a glance.'">
        <x-slot:actions>
            <x-button :href="route('platform.schools.create')" icon="plus">New school</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4" data-stagger>
        @foreach ($stats as $stat)
            <x-stat-card :label="$stat['label']" :value="$stat['value']" :hint="$stat['hint']" :icon="$stat['icon']" :tone="$stat['tone']" :href="$stat['url'] ?? null" />
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="flex min-w-0 flex-col gap-6">
            <x-card>
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="m-0 text-base font-semibold">Schools active each day</h2>
                    <span class="text-sm text-muted">Any sign-in or change, last 30 days</span>
                </div>
                <x-report.columns class="mt-5" tone="naija" label="Schools active each day" :bars="$activeDaily" />
            </x-card>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <x-card>
                    <h2 class="m-0 text-base font-semibold">New schools by month</h2>
                    <x-report.columns class="mt-5" label="New schools by month" :bars="$signups" />
                </x-card>
                <x-card>
                    <h2 class="m-0 text-base font-semibold">Schools by status</h2>
                    <div class="mt-5">
                        <x-report.bars :rows="collect($statusCounts)->mapWithKeys(fn ($count, $status) => [\App\Enums\SchoolStatus::from($status)->label() => $count])->all()" />
                    </div>
                </x-card>
            </div>

            <x-card :padding="false">
                <div class="flex items-center justify-between border-b border-line px-5 py-4">
                    <div>
                        <h2 class="m-0 text-base font-semibold">Trials ending soon</h2>
                        <p class="m-0 text-sm text-muted">Talk to these schools before they lose access.</p>
                    </div>
                    <a href="{{ route('platform.schools.index', ['status' => 'trial']) }}" class="text-sm font-semibold text-brand-600 hover:text-ink">All trials</a>
                </div>
                @forelse ($trialsEnding as $school)
                    @php
                        $daysLeft = (int) now()->startOfDay()->diffInDays($school->trial_ends_at->copy()->startOfDay(), false);
                    @endphp
                    <a href="{{ route('platform.schools.show', $school) }}#settings" class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-b-0 hover:bg-canvas">
                        <x-school-crest :school="$school" class="size-9 flex-none rounded-lg text-xs" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold">{{ $school->name }}</span>
                            <span class="block truncate text-sm text-muted">{{ number_format($school->students_count) }} students · last active {{ $school->last_active_at?->diffForHumans() ?? 'never' }}</span>
                        </span>
                        <x-badge :tone="$daysLeft <= 3 ? 'danger' : 'gold'">{{ $daysLeft < 0 ? 'Ended' : ($daysLeft === 0 ? 'Ends today' : $daysLeft.' '.str('day')->plural($daysLeft).' left') }}</x-badge>
                    </a>
                @empty
                    <x-empty-state icon="hourglass" title="No trials ending in the next two weeks" />
                @endforelse
            </x-card>
        </div>

        <aside class="flex min-w-0 flex-col gap-6">
            <x-card>
                <div class="flex items-center justify-between gap-2">
                    <h2 class="m-0 text-base font-semibold">Platform status</h2>
                    <a href="{{ route('platform.settings.edit') }}" class="text-sm font-semibold text-brand-600 hover:text-ink">Settings</a>
                </div>
                <dl class="m-0 mt-4 flex flex-col gap-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="flex items-center gap-2 text-muted"><x-lucide-credit-card class="size-4" aria-hidden="true" /> Paystack</dt>
                        <dd class="m-0"><x-badge :tone="$platform['paystack'][1]">{{ $platform['paystack'][0] }}</x-badge></dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="flex items-center gap-2 text-muted"><x-lucide-mail class="size-4" aria-hidden="true" /> Email</dt>
                        <dd class="m-0"><x-badge :tone="$platform['email'][1]">{{ $platform['email'][0] }}</x-badge></dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="flex items-center gap-2 text-muted"><x-lucide-list-checks class="size-4" aria-hidden="true" /> Jobs waiting</dt>
                        <dd class="m-0 font-semibold">{{ number_format($platform['queuedJobs']) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="flex items-center gap-2 text-muted"><x-lucide-circle-alert class="size-4" aria-hidden="true" /> Failed jobs</dt>
                        <dd class="m-0"><x-badge :tone="$platform['failedJobs'] > 0 ? 'danger' : 'naija'">{{ number_format($platform['failedJobs']) }}</x-badge></dd>
                    </div>
                </dl>
            </x-card>

            <x-card :padding="false">
                <div class="flex items-center justify-between border-b border-line px-5 py-4">
                    <h2 class="m-0 text-base font-semibold">Newest schools</h2>
                    <a href="{{ route('platform.schools.index') }}" class="text-sm font-semibold text-brand-600 hover:text-ink">All</a>
                </div>
                @forelse ($recentSchools as $school)
                    <a href="{{ route('platform.schools.show', $school) }}" class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-b-0 hover:bg-canvas">
                        <x-school-crest :school="$school" class="size-9 flex-none rounded-lg text-xs" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold">{{ $school->name }}</span>
                            <span class="block truncate text-xs text-muted">Joined {{ $school->created_at->diffForHumans() }} · {{ number_format($school->students_count) }} students</span>
                        </span>
                        <x-badge :tone="$school->status->tone()">{{ $school->status->label() }}</x-badge>
                    </a>
                @empty
                    <x-empty-state icon="school" title="No schools yet" />
                @endforelse
            </x-card>

            <x-card :padding="false">
                <div class="flex items-center justify-between border-b border-line px-5 py-4">
                    <h2 class="m-0 text-base font-semibold">Latest leads</h2>
                    <a href="{{ route('platform.leads.index') }}" class="text-sm font-semibold text-brand-600 hover:text-ink">All</a>
                </div>
                @forelse ($recentLeads as $lead)
                    <div class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-b-0">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold">{{ $lead->school_name ?? $lead->name ?? $lead->email ?? $lead->phone }}</div>
                            <div class="truncate text-xs text-muted">{{ $lead->kind->label() }} · {{ $lead->created_at->diffForHumans() }}</div>
                        </div>
                        <x-badge :tone="$lead->handled_at ? 'neutral' : 'naija'">{{ $lead->handled_at ? 'Handled' : 'New' }}</x-badge>
                    </div>
                @empty
                    <x-empty-state icon="inbox" title="No leads yet" body="Demo requests from the website show up here." />
                @endforelse
            </x-card>
        </aside>
    </div>
</x-layouts.app>
