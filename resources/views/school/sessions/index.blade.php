<x-layouts.app title="Sessions & Terms">
    <x-page-header kicker="Academics" title="Sessions & Terms" description="Each session has a First, Second and Third Term. The current term is the one scores, attendance and fees go into.">
        <x-slot:actions>
            <x-button icon="plus" type="button" x-data x-on:click="$dispatch('open-drawer', 'new-session')">Start a new session</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($sessions->isNotEmpty())
    <x-tabs :tabs="$sessions->mapWithKeys(fn ($s) => ['s'.$s->id => $s->name.($s->is_current ? ' · current' : '')])->all()" :default="'s'.($sessions->firstWhere('is_current', true)?->id ?? $sessions->first()->id)" label="Sessions">
    @foreach ($sessions as $session)
    <x-tab :name="'s'.$session->id">
        <x-card :padding="false">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-4">
                <div class="flex items-center gap-3">
                    <h2 class="m-0 text-xl font-bold tracking-[-0.02em]">{{ $session->name }}</h2>
                    @if ($session->is_current)
                        <x-badge tone="brand">Current session</x-badge>
                    @endif
                </div>
                <span class="text-sm text-muted">{{ $session->starts_on->format('j M Y') }} – {{ $session->ends_on->format('j M Y') }} · {{ number_format($session->enrolments_count) }} students enrolled</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] border-collapse text-left text-[15px]">
                    <thead>
                        <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                            <th scope="col" class="px-6 py-3 font-semibold">Term</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Resumption</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Mid-term break</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Vacation</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($session->terms as $term)
                            @php($bag = 'term'.$term->id)
                            <tr @class(['border-t border-line', 'bg-brand-50/60' => $term->is_current])>
                                <th scope="row" class="px-6 py-3.5 text-left font-semibold">
                                    {{ $term->name }}
                                    @if ($term->is_current)
                                        <x-badge tone="brand" class="ml-1.5">Current</x-badge>
                                    @endif
                                </th>
                                <td class="px-3 py-3.5">{{ $term->starts_on->format('d/m/Y') }}</td>
                                <td class="px-3 py-3.5 text-muted">
                                    {{ $term->mid_term_starts_on ? $term->mid_term_starts_on->format('d/m').' – '.$term->mid_term_ends_on?->format('d/m') : '—' }}
                                </td>
                                <td class="px-3 py-3.5">{{ $term->ends_on->format('d/m/Y') }}</td>
                                <td class="px-3 py-3.5">
                                    <x-badge :tone="$term->isClosed() ? 'neutral' : 'naija'">{{ $term->status->label() }}</x-badge>
                                </td>
                                <td class="px-6 py-3.5">
                                    <div class="flex flex-wrap items-center justify-end gap-1.5">
                                        @unless ($term->is_current)
                                            <form method="POST" action="{{ route('school.terms.current', $term) }}">
                                                @csrf
                                                <x-button variant="soft" size="sm">Make current</x-button>
                                            </form>
                                        @endunless
                                        <x-button variant="ghost" size="sm" icon="pencil" type="button" x-data x-on:click="$dispatch('open-drawer', 'edit-term-{{ $term->id }}')">Edit</x-button>
                                        <form method="POST" action="{{ route('school.terms.status', $term) }}">
                                            @csrf
                                            <x-button variant="ghost" size="sm" :icon="$term->isClosed() ? 'lock-open' : 'lock'">{{ $term->isClosed() ? 'Reopen' : 'Close' }}</x-button>
                                        </form>
                                    </div>

                                    <x-drawer name="edit-term-{{ $term->id }}" :title="$term->name.' · '.$session->name" description="Dates appear on report cards and the parents’ portal." :show="$errors->getBag($bag)->isNotEmpty()">
                                        <form method="POST" action="{{ route('school.terms.update', $term) }}" class="flex flex-col gap-4 text-left">
                                            @csrf
                                            @method('PATCH')
                                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <x-field label="Resumption" name="starts_on" id="starts_on_{{ $term->id }}" type="date" :value="$term->starts_on->toDateString()" :bag="$bag" required />
                                                <x-field label="Vacation" name="ends_on" id="ends_on_{{ $term->id }}" type="date" :value="$term->ends_on->toDateString()" :bag="$bag" required />
                                                <x-field label="Mid-term break starts" name="mid_term_starts_on" id="mid_start_{{ $term->id }}" type="date" :value="$term->mid_term_starts_on?->toDateString()" :bag="$bag" />
                                                <x-field label="Mid-term break ends" name="mid_term_ends_on" id="mid_end_{{ $term->id }}" type="date" :value="$term->mid_term_ends_on?->toDateString()" :bag="$bag" />
                                            </div>
                                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <x-field label="Next term begins" name="next_term_begins_on" id="next_{{ $term->id }}" type="date" :value="$term->next_term_begins_on?->toDateString()" :bag="$bag" hint="Printed on report cards." />
                                                <x-field label="Times school opened" name="school_days" id="school_days_{{ $term->id }}" type="number" min="1" max="366" inputmode="numeric" :value="$term->school_days" :bag="$bag" hint="For attendance on report cards." />
                                            </div>
                                            <x-button variant="brand" class="self-start">Save term</x-button>
                                        </form>
                                    </x-drawer>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    </x-tab>
    @endforeach
    </x-tabs>
    @else
        <x-card>
            <x-empty-state icon="calendar-range" title="No session yet" body="Start your first session. We’ll create First, Second and Third Term with the usual Nigerian dates, and you can adjust them.">
                <x-button icon="plus" type="button" x-data x-on:click="$dispatch('open-drawer', 'new-session')">Start a session</x-button>
            </x-empty-state>
        </x-card>
    @endif

    <x-drawer name="new-session" title="Start a new session" description="Three terms are created with typical dates: mid-September, early January and late April." :show="$errors->has('start_year')">
        <form method="POST" action="{{ route('school.sessions.store') }}" class="flex flex-col gap-4">
            @csrf
            <x-select-field
                label="Session"
                name="start_year"
                :value="$nextStartYear"
                :options="collect(range(now()->year - 2, now()->year + 1))->mapWithKeys(fn ($year) => [$year => $year.'/'.($year + 1)])->all()"
            />
            <label class="flex items-center gap-2.5 text-[15px]">
                <input type="hidden" name="make_current" value="0">
                <input type="checkbox" name="make_current" value="1" class="size-4 accent-brand-600" @checked($sessions->isEmpty())>
                Make its First Term the current term
            </label>
            <x-button variant="brand" class="self-start">Create session</x-button>
        </form>
    </x-drawer>
</x-layouts.app>
