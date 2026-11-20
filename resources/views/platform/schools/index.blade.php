<x-layouts.app title="Schools">
    <x-page-header kicker="Platform console" title="Schools" description="Every school on Ta’aleem. Open one to see it the way its staff do.">
        <x-slot:actions>
            <x-button :href="route('platform.schools.create')" icon="plus">New school</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        <form method="GET" class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4" role="search">
            <label for="search" class="sr-only">Search schools</label>
            <div class="relative min-w-[220px] flex-1">
                <x-lucide-search class="pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2 text-muted" aria-hidden="true" />
                <input id="search" name="search" type="search" value="{{ $search }}" placeholder="Search by name, slug or state" class="field pl-10">
            </div>
            <x-button variant="secondary">Search</x-button>
        </form>

        @if ($schools->isEmpty())
            <x-empty-state icon="school" title="{{ $search ? 'No schools match “'.$search.'”' : 'No schools yet' }}" body="Create a school and we’ll email its admin a link to choose a password." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] border-collapse text-left text-[15px]">
                    <thead>
                        <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                            <th scope="col" class="px-5 py-3 font-semibold">School</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Type</th>
                            <th scope="col" class="px-3 py-3 font-semibold">State</th>
                            <th scope="col" class="px-3 py-3 font-semibold">People</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-5 py-3 font-semibold"><span class="sr-only">Open</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($schools as $school)
                            <tr class="border-t border-line">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <span class="grid size-9 flex-none place-items-center rounded-lg bg-brand-50 text-sm font-bold text-brand-600">{{ $school->initials() }}</span>
                                        <div class="min-w-0">
                                            <div class="truncate font-semibold">{{ $school->name }}</div>
                                            <div class="text-sm text-muted">/{{ $school->slug }} · {{ $school->short_code }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5 text-muted">{{ $school->school_type->label() }}</td>
                                <td class="px-3 py-3.5 text-muted">{{ $school->state ?? '—' }}</td>
                                <td class="px-3 py-3.5">{{ number_format($school->users_count) }}</td>
                                <td class="px-3 py-3.5">
                                    <x-badge :tone="$school->status->allowsAccess() ? 'brand' : 'danger'">{{ $school->status->label() }}</x-badge>
                                    @if ($school->trial_ends_at && $school->status === \App\Enums\SchoolStatus::Trial)
                                        <div class="mt-1 text-xs text-muted">Ends {{ $school->trial_ends_at->format('d/m/Y') }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <a href="{{ route('school.dashboard', $school) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-ink">Open <x-lucide-arrow-right class="size-4" aria-hidden="true" /></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($schools->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $schools->links() }}</div>
            @endif
        @endif
    </x-card>
</x-layouts.app>
