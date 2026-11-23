<x-layouts.app title="Leads">
    <x-page-header kicker="Platform console" title="Leads from the website" description="People who ask for a demo, trial or help on the public site land here for follow-up." />

    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-stat-card label="New to handle" :value="number_format($stats['new'])" hint="Not marked handled yet" icon="inbox" tone="sky" />
        <x-stat-card label="Demos" :value="number_format($stats['demos'])" hint="Book a demo" icon="calendar" tone="brand" />
        <x-stat-card label="Trials" :value="number_format($stats['trials'])" hint="Free trial requests" icon="sparkles" tone="naija" />
        <x-stat-card label="Questions" :value="number_format($stats['questions'])" hint="Contact / help" icon="message-circle" tone="gold" />
    </div>

    <x-card>
        <h2 class="m-0 text-base font-semibold">How leads arrive</h2>
        <p class="m-0 mt-1 text-sm text-muted">Nothing is created by hand here — forms on the marketing site write a lead when someone submits.</p>
        <ul class="m-0 mt-4 list-disc space-y-2 pl-5 text-[15px] text-ink">
            <li><span class="font-semibold">Home page (top)</span> — “Book a demo” / trial form in the hero (<code class="text-sm">hero</code>).</li>
            <li><span class="font-semibold">Home page (bottom)</span> — CTA section near the footer (<code class="text-sm">cta</code>).</li>
            <li><span class="font-semibold">Contact page</span> — questions and general enquiries (<code class="text-sm">contact</code>).</li>
        </ul>
        <p class="m-0 mt-4 text-sm text-muted">Each lead stores who they are, school size, their message, and which form they used. Use notes for call outcomes, then mark handled when you’re done. Create the school from <a href="{{ route('platform.schools.create') }}" class="font-semibold text-brand-600 hover:text-ink">Add school</a> when they convert.</p>
    </x-card>

    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex gap-1 self-start rounded-xl border border-line bg-white p-1">
                <a href="{{ route('platform.leads.index', array_filter(['kind' => $kindFilter, 'source' => $sourceFilter])) }}" @class(['rounded-lg px-3.5 py-2 text-sm font-semibold', 'bg-ink text-white' => ! $showHandled, 'text-muted hover:text-ink' => $showHandled])>New</a>
                <a href="{{ route('platform.leads.index', array_filter(['handled' => 1, 'kind' => $kindFilter, 'source' => $sourceFilter])) }}" @class(['rounded-lg px-3.5 py-2 text-sm font-semibold', 'bg-ink text-white' => $showHandled, 'text-muted hover:text-ink' => ! $showHandled])>All</a>
            </div>

            <div class="flex flex-wrap gap-1">
                <a href="{{ route('platform.leads.index', array_filter(['handled' => $showHandled ? 1 : null, 'source' => $sourceFilter])) }}" @class(['rounded-lg border px-3 py-1.5 text-sm font-semibold', 'border-ink bg-ink text-white' => blank($kindFilter), 'border-line bg-white text-muted hover:text-ink' => filled($kindFilter)])>Any type</a>
                @foreach ($kinds as $kind)
                    <a href="{{ route('platform.leads.index', array_filter(['handled' => $showHandled ? 1 : null, 'kind' => $kind->value, 'source' => $sourceFilter])) }}" @class(['rounded-lg border px-3 py-1.5 text-sm font-semibold', 'border-ink bg-ink text-white' => $kindFilter === $kind->value, 'border-line bg-white text-muted hover:text-ink' => $kindFilter !== $kind->value])>{{ $kind->label() }}</a>
                @endforeach
            </div>
        </div>

        <div class="flex flex-wrap gap-1">
            <a href="{{ route('platform.leads.index', array_filter(['handled' => $showHandled ? 1 : null, 'kind' => $kindFilter])) }}" @class(['rounded-lg border px-3 py-1.5 text-sm font-semibold', 'border-ink bg-ink text-white' => blank($sourceFilter), 'border-line bg-white text-muted hover:text-ink' => filled($sourceFilter)])>Any page</a>
            @foreach ($sources as $sourceKey => $sourceLabel)
                <a href="{{ route('platform.leads.index', array_filter(['handled' => $showHandled ? 1 : null, 'kind' => $kindFilter, 'source' => $sourceKey])) }}" @class(['rounded-lg border px-3 py-1.5 text-sm font-semibold', 'border-ink bg-ink text-white' => $sourceFilter === $sourceKey, 'border-line bg-white text-muted hover:text-ink' => $sourceFilter !== $sourceKey])>{{ $sourceLabel }}</a>
            @endforeach
        </div>
    </div>

    <x-card :padding="false">
        @forelse ($leads as $lead)
            <div class="border-b border-line px-6 py-5 last:border-b-0">
                <div class="flex flex-wrap items-start gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">{{ $lead->school_name ?? $lead->name ?? 'Unnamed lead' }}</span>
                            <x-badge tone="brand">{{ $lead->kind->label() }}</x-badge>
                            @if ($lead->handled_at)
                                <x-badge tone="success">Handled {{ $lead->handled_at->format('j M Y') }}</x-badge>
                            @endif
                            @if ($lead->student_count)
                                <x-badge>{{ $lead->student_count }} students</x-badge>
                            @endif
                        </div>
                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted">
                            @if ($lead->name && $lead->school_name)<span>{{ $lead->name }}</span>@endif
                            @if ($lead->email)<a href="mailto:{{ $lead->email }}" class="text-brand-600 hover:text-ink">{{ $lead->email }}</a>@endif
                            @if ($lead->phone)<a href="tel:{{ $lead->phone }}" class="text-brand-600 hover:text-ink">{{ $lead->phone }}</a>@endif
                            <span>{{ $lead->sourceLabel() }}</span>
                            <time datetime="{{ $lead->created_at->toIso8601String() }}">{{ $lead->created_at->format('d/m/Y H:i') }}</time>
                        </div>
                        @if ($lead->message)
                            <p class="m-0 mt-2 max-w-3xl text-[15px] whitespace-pre-line">{{ $lead->message }}</p>
                        @endif
                    </div>
                    <div class="flex flex-none flex-wrap gap-2">
                        @if ($lead->email)
                            <x-button :href="'mailto:'.$lead->email.'?subject='.rawurlencode('Re: Ta’aleem — '.$lead->kind->label())" variant="secondary" size="sm" icon="mail">Email</x-button>
                        @endif
                        <form method="POST" action="{{ route('platform.leads.update', $lead) }}">
                            @csrf
                            @method('PATCH')
                            <x-button :variant="$lead->handled_at ? 'ghost' : 'secondary'" size="sm" :icon="$lead->handled_at ? 'history' : 'check'">
                                {{ $lead->handled_at ? 'Mark as new' : 'Mark handled' }}
                            </x-button>
                        </form>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[12rem_minmax(0,1fr)]">
                    <form method="POST" action="{{ route('platform.leads.update', $lead) }}" class="flex flex-col gap-1.5">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="action" value="kind">
                        <label for="kind-{{ $lead->id }}" class="text-xs font-semibold tracking-wide text-muted uppercase">Type</label>
                        <select id="kind-{{ $lead->id }}" name="kind" class="field" onchange="this.form.requestSubmit()">
                            @foreach ($kinds as $kind)
                                <option value="{{ $kind->value }}" @selected($lead->kind === $kind)>{{ $kind->label() }}</option>
                            @endforeach
                        </select>
                    </form>

                    <form method="POST" action="{{ route('platform.leads.update', $lead) }}" class="flex flex-col gap-1.5">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="action" value="notes">
                        <label for="notes-{{ $lead->id }}" class="text-xs font-semibold tracking-wide text-muted uppercase">Internal notes</label>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start">
                            <textarea id="notes-{{ $lead->id }}" name="notes" rows="2" class="field min-w-0 flex-1" placeholder="Call outcome, follow-up date, who owns this…">{{ old('notes', $lead->notes) }}</textarea>
                            <x-button variant="secondary" size="sm" icon="check" class="flex-none">Save notes</x-button>
                        </div>
                    </form>
                </div>
            </div>
        @empty
            <x-empty-state icon="inbox" title="{{ $showHandled ? 'No leads match these filters' : 'You’re all caught up' }}" body="New demo, trial and contact submissions from the website will appear here." />
        @endforelse

        @if ($leads->hasPages())
            <div class="border-t border-line px-6 py-4">{{ $leads->links() }}</div>
        @endif
    </x-card>
</x-layouts.app>
