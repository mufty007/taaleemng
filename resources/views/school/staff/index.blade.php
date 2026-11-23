<x-layouts.app title="Staff">
    <x-page-header kicker="People" title="Staff" :description="number_format($counts['all']).' people on the staff list · '.number_format($counts['teaching']).' teaching, '.number_format($counts['support']).' support.'">
        @if ($canManage)
            <x-slot:actions>
                <x-button variant="secondary" icon="id-card" :href="route('school.id-cards.index').'#staff'">Staff ID cards</x-button>
                <x-button :href="route('school.staff.create')" icon="user-plus">Add staff</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @php
        $query = array_filter(['search' => $search ?: null, 'department' => $department ?: null, 'status' => $status?->value]);
        $statusTone = fn (\App\Enums\StaffStatus $value): array => match ($value) {
            \App\Enums\StaffStatus::Active => ['bg-naija-50 text-naija-700', 'bg-naija'],
            \App\Enums\StaffStatus::OnLeave => ['bg-gold-50 text-gold', 'bg-gold'],
            \App\Enums\StaffStatus::Exited => ['bg-chip text-muted', 'bg-placeholder'],
        };
    @endphp

    <x-card :padding="false">
        {{-- Filters: who (segmented), then search, department and status in one row. --}}
        <div class="flex flex-col gap-3 border-b border-line px-4 py-4 sm:px-5">
            <nav class="-mx-1 flex gap-1 overflow-x-auto px-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" aria-label="Staff type">
                @foreach (['' => ['All', $counts['all']], 'teaching' => ['Teaching', $counts['teaching']], 'support' => ['Support staff', $counts['support']]] as $value => [$text, $count])
                    @php
                        $current = ($type ?? '') === $value;
                    @endphp
                    <a
                        href="{{ route('school.staff.index', array_filter([...$query, 'type' => $value ?: null])) }}"
                        @if ($current) aria-current="page" @endif
                        @class(['inline-flex flex-none items-center gap-2 rounded-full border px-3.5 py-1.5 text-sm font-semibold transition', 'border-ink bg-ink text-white' => $current, 'border-line bg-white text-muted hover:text-ink' => ! $current])
                    >
                        {{ $text }}
                        <span @class(['rounded-full px-1.5 text-xs', 'bg-white/20' => $current, 'bg-chip' => ! $current])>{{ number_format($count) }}</span>
                    </a>
                @endforeach
            </nav>

            <form method="GET" class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center" role="search" x-data>
                @if ($type)
                    <input type="hidden" name="type" value="{{ $type }}">
                @endif
                <label for="search" class="sr-only">Search staff</label>
                <div class="relative col-span-2 min-w-[220px] flex-1">
                    <x-lucide-search class="pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2 text-muted" aria-hidden="true" />
                    <input id="search" name="search" type="search" value="{{ $search }}" placeholder="Name, job title or staff number" class="field py-2.5 pl-10">
                </div>
                <label for="department" class="sr-only">Department</label>
                <div class="relative sm:w-48">
                    <select id="department" name="department" class="field appearance-none py-2.5 pr-9" x-on:change="$el.form.requestSubmit()">
                        <option value="">All departments</option>
                        @foreach ($departments as $option)
                            <option value="{{ $option }}" @selected($department === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                    <x-lucide-chevron-down class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted" aria-hidden="true" />
                </div>
                <label for="status" class="sr-only">Status</label>
                <div class="relative sm:w-40">
                    <select id="status" name="status" class="field appearance-none py-2.5 pr-9" x-on:change="$el.form.requestSubmit()">
                        <option value="">Any status</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($status?->value === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-lucide-chevron-down class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted" aria-hidden="true" />
                </div>
                <x-button variant="secondary" class="col-span-2 sm:col-span-1">Search</x-button>
                @if ($filtered)
                    <a href="{{ route('school.staff.index') }}" class="col-span-2 text-center text-sm font-semibold text-brand-600 hover:text-ink sm:col-span-1">Clear</a>
                @endif
            </form>
        </div>

        @if ($staff->isEmpty())
            <x-empty-state icon="search-x" :title="$filtered ? 'No one matches those filters' : 'No staff yet'" :body="$filtered ? 'Try another name, or clear the filters.' : 'Add your principal, bursar and teachers so they can sign in.'">
                @if ($filtered)
                    <x-button variant="secondary" :href="route('school.staff.index')">Clear filters</x-button>
                @elseif ($canManage)
                    <x-button :href="route('school.staff.create')" icon="user-plus">Add staff</x-button>
                @endif
            </x-empty-state>
        @else
            {{-- Phones: one tappable row per person. --}}
            <ul class="m-0 list-none p-0 md:hidden">
                @foreach ($staff as $member)
                    @php
                        [$pill, $dot] = $statusTone($member->status);
                    @endphp
                    <li class="border-b border-line last:border-b-0">
                        <a href="{{ route('school.staff.show', $member) }}" class="flex items-center gap-3 px-4 py-3 active:bg-chip">
                            <x-avatar :person="$member" class="size-11 rounded-full text-sm" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold">{{ $member->fullName() }}</span>
                                <span class="block truncate text-sm text-muted">{{ $member->designation }}{{ $member->department ? ' · '.$member->department : '' }}</span>
                            </span>
                            @if ($member->status !== \App\Enums\StaffStatus::Active)
                                <span class="inline-flex flex-none items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold {{ $pill }}"><span class="size-1.5 rounded-full {{ $dot }}" aria-hidden="true"></span>{{ $member->status->label() }}</span>
                            @endif
                            <x-lucide-chevron-right class="size-4 flex-none text-muted" aria-hidden="true" />
                        </a>
                    </li>
                @endforeach
            </ul>

            {{-- Larger screens: the full table. --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[820px] border-collapse text-left text-[15px]">
                    <thead>
                        <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                            <th scope="col" class="px-5 py-3 font-semibold">Name</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Job</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Teaches</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Phone</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-5 py-3 font-semibold"><span class="sr-only">Open</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($staff as $member)
                            @php
                        [$pill, $dot] = $statusTone($member->status);
                    @endphp
                            <tr class="group border-t border-line hover:bg-canvas">
                                <td class="px-5 py-3">
                                    <a href="{{ route('school.staff.show', $member) }}" class="flex items-center gap-3">
                                        <x-avatar :person="$member" class="size-10 rounded-full text-xs" />
                                        <span class="min-w-0">
                                            <span class="block truncate font-semibold group-hover:text-brand-600">{{ $member->fullName() }}</span>
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
                                        <span class="mt-1 block text-muted">{{ $member->class_subjects_count }} class {{ \Illuminate\Support\Str::plural('subject', $member->class_subjects_count) }}</span>
                                    @elseif ($member->formClasses->isEmpty())
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-sm whitespace-nowrap">
                                    @if ($member->phone)
                                        <a href="tel:{{ $member->phone }}" class="font-medium hover:text-brand-600">{{ \App\Models\User::displayPhone($member->phone) }}</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                    @if ($member->email)
                                        <span class="block max-w-[220px] truncate text-muted">{{ $member->email }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap {{ $pill }}"><span class="size-1.5 rounded-full {{ $dot }}" aria-hidden="true"></span>{{ $member->status->label() }}</span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('school.staff.show', $member) }}" class="inline-grid size-8 place-items-center rounded-lg text-muted hover:bg-chip hover:text-ink" aria-label="Open {{ $member->fullName() }}">
                                        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($staff->hasPages())
            <div class="border-t border-line px-5 py-4">{{ $staff->links() }}</div>
        @endif
    </x-card>
</x-layouts.app>
