<x-layouts.app title="Staff">
    <x-page-header kicker="People" title="Staff" :description="number_format($staff->total()).' people on the staff list.'">
        @if ($canManage)
            <x-slot:actions>
                <x-button :href="route('school.staff.create')" icon="user-plus">Add staff</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <x-card :padding="false">
        <form method="GET" class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4" role="search">
            <label for="search" class="sr-only">Search staff</label>
            <div class="relative min-w-[220px] flex-1">
                <x-lucide-search class="pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2 text-muted" aria-hidden="true" />
                <input id="search" name="search" type="search" value="{{ $search }}" placeholder="Name, role or staff number" class="field pl-10">
            </div>
            <x-button variant="secondary">Search</x-button>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] border-collapse text-left text-[15px]">
                <thead>
                    <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                        <th scope="col" class="px-5 py-3 font-semibold">Name</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Role</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Teaches</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Contact</th>
                        <th scope="col" class="px-5 py-3 font-semibold"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($staff as $member)
                        <tr class="border-t border-line hover:bg-canvas">
                            <td class="px-5 py-3">
                                <a href="{{ route('school.staff.show', $member) }}" class="flex items-center gap-3">
                                    <span class="grid size-9 flex-none place-items-center rounded-full bg-ink text-xs font-semibold text-white">{{ \Illuminate\Support\Str::upper(substr($member->first_name, 0, 1).substr($member->last_name, 0, 1)) }}</span>
                                    <span class="min-w-0">
                                        <span class="block truncate font-semibold">{{ $member->fullName() }}</span>
                                        <span class="block font-mono text-xs text-muted">{{ $member->staff_no }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="px-3 py-3">
                                <span class="block">{{ $member->designation }}</span>
                                @if ($member->department)
                                    <span class="text-sm text-muted">{{ $member->department }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-sm">
                                @if ($member->formClasses->isNotEmpty())
                                    <x-badge tone="brand">Form teacher · {{ $member->formClasses->map->displayName()->join(', ') }}</x-badge>
                                @endif
                                @if ($member->class_subjects_count > 0)
                                    <span class="mt-1 block text-muted">{{ $member->class_subjects_count }} class subjects</span>
                                @elseif ($member->formClasses->isEmpty())
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-sm">
                                @if ($member->phone)<a href="tel:{{ $member->phone }}" class="block text-brand-600 hover:text-ink">{{ $member->phone }}</a>@endif
                                @if ($member->email)<span class="block truncate text-muted">{{ $member->email }}</span>@endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('school.staff.show', $member) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-ink">Open <x-lucide-arrow-right class="size-4" aria-hidden="true" /></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($staff->hasPages())
            <div class="border-t border-line px-5 py-4">{{ $staff->links() }}</div>
        @endif
    </x-card>
</x-layouts.app>
