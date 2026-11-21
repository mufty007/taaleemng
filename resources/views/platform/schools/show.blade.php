<x-layouts.app :title="$school->name">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('platform.schools.index') }}" class="hover:text-ink">Schools</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">{{ $school->name }}</span>
    </div>

    <x-page-header kicker="Platform console" :title="$school->name" :description="$school->school_type->label().($school->state ? ' · '.$school->state : '').' · code '.$school->short_code.' · /'.$school->slug">
        <x-slot:actions>
            <x-badge :tone="$school->allowsAccess() ? 'brand' : 'danger'">{{ $school->status->label() }}</x-badge>
            <x-button :href="route('school.dashboard', $school)" icon="arrow-right">Open school</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" data-stagger>
        @foreach ($stats as $stat)
            <x-stat-card :label="$stat['label']" :value="number_format($stat['value'])" :icon="$stat['icon']" :tone="$stat['tone']" />
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
        <x-card :padding="false">
            <h2 class="m-0 border-b border-line px-6 py-4 text-lg font-semibold">Recent activity</h2>
            @forelse ($activity as $entry)
                <div class="flex items-start gap-3 border-b border-line px-6 py-3 last:border-b-0">
                    <span class="mt-0.5 grid size-8 flex-none place-items-center rounded-full bg-chip text-[11px] font-semibold">{{ $entry->causer?->initials() ?? '·' }}</span>
                    <div class="min-w-0 flex-1 text-sm">
                        <span class="font-semibold">{{ $entry->causer?->name ?? 'System' }}</span>
                        <span class="text-muted">{{ $entry->description }}</span>
                        <div class="text-xs text-muted">{{ $entry->created_at->format('d/m/Y H:i') }} · {{ $entry->ip_address ?? 'no IP' }}</div>
                    </div>
                </div>
            @empty
                <x-empty-state icon="history" title="No activity yet" />
            @endforelse
        </x-card>

        <div class="flex flex-col gap-6">
            <x-card>
                <h2 class="m-0 text-lg font-semibold">Account status</h2>
                <p class="m-0 mt-1 text-sm text-muted">Suspending blocks staff, parents and students from signing in. Nothing is deleted.</p>
                <form method="POST" action="{{ route('platform.schools.status', $school) }}" class="mt-4 flex flex-wrap items-end gap-3">
                    @csrf
                    @method('PATCH')
                    <div class="min-w-[180px] flex-1">
                        <x-select-field label="Status" name="status" :value="$school->status->value" :options="collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all()" />
                    </div>
                    <x-button variant="secondary">Update</x-button>
                </form>
                @if ($school->trial_ends_at && $school->status === \App\Enums\SchoolStatus::Trial)
                    <p class="m-0 mt-3 text-sm text-muted">Trial ends {{ $school->trial_ends_at->format('d/m/Y') }}.</p>
                @endif
            </x-card>

            <x-card>
                <h2 class="m-0 text-lg font-semibold">Current term</h2>
                <p class="m-0 mt-2 text-[15px]">{{ $currentTerm ? $currentTerm->name.' · '.$currentTerm->academicSession->name : 'Not set up yet' }}</p>
            </x-card>

            <x-card :padding="false">
                <h2 class="m-0 border-b border-line px-6 py-4 text-lg font-semibold">School admins</h2>
                @foreach ($admins as $admin)
                    <div class="flex items-center gap-3 border-b border-line px-6 py-3 last:border-b-0">
                        <span class="grid size-9 flex-none place-items-center rounded-full bg-ink text-xs font-semibold text-white">{{ $admin->initials() }}</span>
                        <div class="min-w-0">
                            <div class="truncate font-semibold">{{ $admin->name }}</div>
                            <div class="truncate text-sm text-muted">{{ $admin->email ?? $admin->phone }} · {{ $admin->last_login_at ? 'last login '.$admin->last_login_at->diffForHumans() : 'never logged in' }}</div>
                        </div>
                    </div>
                @endforeach
            </x-card>
        </div>
    </div>
</x-layouts.app>
