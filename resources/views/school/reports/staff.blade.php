<x-layouts.app title="Staff report">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.reports.index') }}" class="hover:text-ink">Reports</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">Staff</span>
    </div>

    <x-page-header kicker="Reports" title="Staff headcount" :description="$active.' active of '.$members->count().' on file'">
        <x-slot:actions>
            <x-button data-native variant="secondary" icon="printer" type="button" x-data x-on:click="window.print()">Print</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-card :padding="false">
            <div class="border-b border-line px-5 py-4">
                <h2 class="m-0 text-base font-semibold">By department</h2>
            </div>
            <ul class="m-0 list-none divide-y divide-line p-0">
                @forelse ($byDepartment as $department => $row)
                    <li class="flex items-center justify-between gap-3 px-5 py-3 text-[15px]">
                        <span class="font-medium">{{ $department }}</span>
                        <span class="text-muted">{{ $row['active'] }} active · {{ $row['total'] }} total</span>
                    </li>
                @empty
                    <li class="px-5 py-8 text-sm text-muted">No staff records yet.</li>
                @endforelse
            </ul>
        </x-card>

        <x-card :padding="false">
            <div class="border-b border-line px-5 py-4">
                <h2 class="m-0 text-base font-semibold">Directory</h2>
            </div>
            <ul class="m-0 max-h-[28rem] list-none divide-y divide-line overflow-y-auto p-0">
                @foreach ($members as $member)
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-[15px]">
                        <span>
                            <span class="font-medium">{{ $member->fullName() }}</span>
                            <span class="block text-xs text-muted">{{ $member->designation ?: '—' }}</span>
                        </span>
                        <x-badge :tone="$member->status === \App\Enums\StaffStatus::Active ? 'naija' : 'neutral'">{{ $member->status->label() }}</x-badge>
                    </li>
                @endforeach
            </ul>
        </x-card>
    </div>
</x-layouts.app>
