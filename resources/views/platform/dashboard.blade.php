<x-layouts.app title="Platform">
    <x-page-header kicker="Platform console" title="All schools at a glance">
        <x-slot:actions>
            <x-button :href="route('platform.schools.create')" icon="plus">New school</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" data-stagger>
        @foreach ($stats as $stat)
            <x-stat-card :label="$stat['label']" :value="number_format($stat['value'])" :icon="$stat['icon']" :tone="$stat['tone']" />
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-card :padding="false">
            <div class="flex items-center justify-between border-b border-line px-6 py-4">
                <h2 class="m-0 text-lg font-semibold">Latest leads</h2>
                <a href="{{ route('platform.leads.index') }}" class="text-sm font-semibold text-brand-600 hover:text-ink">View all</a>
            </div>
            @forelse ($recentLeads as $lead)
                <div class="flex items-center gap-3 border-b border-line px-6 py-3.5 last:border-b-0">
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-semibold">{{ $lead->school_name ?? $lead->name ?? $lead->email ?? $lead->phone }}</div>
                        <div class="truncate text-sm text-muted">{{ $lead->kind->label() }} · {{ $lead->email ?? $lead->phone }} · {{ $lead->created_at->diffForHumans() }}</div>
                    </div>
                    @if ($lead->handled_at)
                        <x-badge>Handled</x-badge>
                    @else
                        <x-badge tone="naija">New</x-badge>
                    @endif
                </div>
            @empty
                <x-empty-state icon="inbox" title="No leads yet" body="Demo requests from the website will show up here." />
            @endforelse
        </x-card>

        <x-card :padding="false">
            <div class="flex items-center justify-between border-b border-line px-6 py-4">
                <h2 class="m-0 text-lg font-semibold">Newest schools</h2>
                <a href="{{ route('platform.schools.index') }}" class="text-sm font-semibold text-brand-600 hover:text-ink">View all</a>
            </div>
            @forelse ($recentSchools as $school)
                <a href="{{ route('school.dashboard', $school) }}" class="flex items-center gap-3 border-b border-line px-6 py-3.5 last:border-b-0 hover:bg-canvas">
                    <span class="grid size-9 flex-none place-items-center rounded-lg bg-brand-50 text-sm font-bold text-brand-600">{{ $school->initials() }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-semibold">{{ $school->name }}</div>
                        <div class="truncate text-sm text-muted">{{ $school->school_type->label() }}{{ $school->state ? ' · '.$school->state : '' }}</div>
                    </div>
                    <x-badge :tone="$school->status->allowsAccess() ? 'brand' : 'danger'">{{ $school->status->label() }}</x-badge>
                </a>
            @empty
                <x-empty-state icon="school" title="No schools yet" />
            @endforelse
        </x-card>
    </div>
</x-layouts.app>
