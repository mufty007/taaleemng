<x-layouts.app title="Schools">
    <x-page-header kicker="Platform console" title="Schools" :description="number_format(array_sum($statusCounts)).' schools on Ta’aleem. Open one to manage it, or to see it the way its staff do.'">
        <x-slot:actions>
            <x-button :href="route('platform.schools.create')" icon="plus">New school</x-button>
        </x-slot:actions>
    </x-page-header>

    @php
        $query = array_filter(['search' => $filters['search'] ?: null, 'state' => $filters['state'] ?: null, 'sort' => $filters['sort'] !== 'newest' ? $filters['sort'] : null]);
    @endphp

    <x-card :padding="false">
        <div class="flex flex-col gap-3 border-b border-line px-4 py-4 sm:px-5">
            <nav class="-mx-1 flex gap-1 overflow-x-auto px-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" aria-label="School status">
                @foreach (['' => ['All', array_sum($statusCounts)], ...collect(\App\Enums\SchoolStatus::cases())->mapWithKeys(fn ($status) => [$status->value => [$status->label(), $statusCounts[$status->value]]])->all()] as $value => [$text, $count])
                    @php
                        $current = ($filters['status']?->value ?? '') === $value;
                    @endphp
                    <a
                        href="{{ route('platform.schools.index', array_filter([...$query, 'status' => $value ?: null])) }}"
                        @if ($current) aria-current="page" @endif
                        @class(['inline-flex flex-none items-center gap-2 rounded-full border px-3.5 py-1.5 text-sm font-semibold transition', 'border-ink bg-ink text-white' => $current, 'border-line bg-white text-muted hover:text-ink' => ! $current])
                    >
                        {{ $text }}
                        <span @class(['rounded-full px-1.5 text-xs', 'bg-white/20' => $current, 'bg-chip' => ! $current])>{{ number_format($count) }}</span>
                    </a>
                @endforeach
            </nav>

            <form method="GET" class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center" role="search" x-data>
                @if ($filters['status'])
                    <input type="hidden" name="status" value="{{ $filters['status']->value }}">
                @endif
                <label for="search" class="sr-only">Search schools</label>
                <div class="relative col-span-2 min-w-[220px] flex-1">
                    <x-lucide-search class="pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2 text-muted" aria-hidden="true" />
                    <input id="search" name="search" type="search" value="{{ $filters['search'] }}" placeholder="Name, code, web address or email" class="field py-2.5 pl-10">
                </div>
                <label for="state" class="sr-only">State</label>
                <div class="relative sm:w-44">
                    <select id="state" name="state" class="field appearance-none py-2.5 pr-9" x-on:change="$el.form.requestSubmit()">
                        <option value="">All states</option>
                        @foreach ($states as $state)
                            <option value="{{ $state }}" @selected($filters['state'] === $state)>{{ $state }}</option>
                        @endforeach
                    </select>
                    <x-lucide-chevron-down class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted" aria-hidden="true" />
                </div>
                <label for="sort" class="sr-only">Sort</label>
                <div class="relative sm:w-44">
                    <select id="sort" name="sort" class="field appearance-none py-2.5 pr-9" x-on:change="$el.form.requestSubmit()">
                        @foreach ($sorts as $value => $label)
                            <option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-lucide-chevron-down class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted" aria-hidden="true" />
                </div>
                <x-button variant="secondary" class="col-span-2 sm:col-span-1">Search</x-button>
                @if ($filtered)
                    <a href="{{ route('platform.schools.index') }}" class="col-span-2 text-center text-sm font-semibold text-brand-600 hover:text-ink sm:col-span-1">Clear</a>
                @endif
            </form>
        </div>

        @if ($schools->isEmpty())
            <x-empty-state icon="school" :title="$filtered ? 'No schools match those filters' : 'No schools yet'" :body="$filtered ? 'Try another name, or clear the filters.' : 'Create a school and we’ll email its admin a link to choose a password.'">
                @if ($filtered)
                    <x-button variant="secondary" :href="route('platform.schools.index')">Clear filters</x-button>
                @endif
            </x-empty-state>
        @else
            {{-- Phones --}}
            <ul class="m-0 list-none p-0 md:hidden">
                @foreach ($schools as $school)
                    <li class="border-b border-line last:border-b-0">
                        <a href="{{ route('platform.schools.show', $school) }}" class="flex items-center gap-3 px-4 py-3 active:bg-chip">
                            <x-school-crest :school="$school" class="size-11 flex-none rounded-xl text-sm" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold">{{ $school->name }}</span>
                                <span class="block truncate text-sm text-muted">{{ number_format($school->students_count) }} students · {{ $school->state ?? $school->school_type->label() }}</span>
                            </span>
                            <x-badge :tone="$school->status->tone()">{{ $school->status->label() }}</x-badge>
                        </a>
                    </li>
                @endforeach
            </ul>

            {{-- Larger screens --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[880px] border-collapse text-left text-[15px]">
                    <thead>
                        <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                            <th scope="col" class="px-5 py-3 font-semibold">School</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-3 py-3 text-right font-semibold">Students</th>
                            <th scope="col" class="px-3 py-3 text-right font-semibold">Staff</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Last active</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Joined</th>
                            <th scope="col" class="px-5 py-3 font-semibold"><span class="sr-only">Open</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($schools as $school)
                            <tr class="group border-t border-line hover:bg-canvas">
                                <td class="px-5 py-3">
                                    <a href="{{ route('platform.schools.show', $school) }}" class="flex items-center gap-3">
                                        <x-school-crest :school="$school" class="size-10 flex-none rounded-xl text-sm" />
                                        <span class="min-w-0">
                                            <span class="block truncate font-semibold group-hover:text-brand-600">{{ $school->name }}</span>
                                            <span class="block truncate text-sm text-muted"><span class="font-mono text-xs">{{ $school->short_code }}</span> · {{ $school->school_type->label() }}{{ $school->state ? ' · '.$school->state : '' }}</span>
                                        </span>
                                    </a>
                                </td>
                                <td class="px-3 py-3">
                                    <x-badge :tone="$school->status->tone()">{{ $school->status->label() }}</x-badge>
                                    @if ($school->status === \App\Enums\SchoolStatus::Trial && $school->trial_ends_at)
                                        <span @class(['mt-1 block text-xs', 'font-semibold text-danger' => $school->trial_ends_at->lte(now()->addDays(3)), 'text-muted' => $school->trial_ends_at->gt(now()->addDays(3))])>{{ $school->trial_ends_at->isPast() ? 'Ended' : 'Ends' }} {{ $school->trial_ends_at->format('j M') }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-right font-semibold tabular-nums">{{ number_format($school->students_count) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ number_format($school->staff_count) }}</td>
                                <td class="px-3 py-3 text-sm text-muted">{{ $school->last_active_at?->diffForHumans() ?? 'Never' }}</td>
                                <td class="px-3 py-3 text-sm text-muted">{{ $school->created_at->format('j M Y') }}</td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('platform.schools.show', $school) }}" class="inline-grid size-8 place-items-center rounded-lg text-muted hover:bg-chip hover:text-ink" aria-label="Open {{ $school->name }}">
                                        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($schools->hasPages())
            <div class="border-t border-line px-5 py-4">{{ $schools->links() }}</div>
        @endif
    </x-card>
</x-layouts.app>
