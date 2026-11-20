<x-layouts.app title="Leads">
    <x-page-header kicker="Platform console" title="Leads from the website" description="Demo bookings, trial requests and questions sent from the landing and contact pages." />

    <div class="flex gap-1 self-start rounded-xl border border-line bg-white p-1">
        <a href="{{ route('platform.leads.index') }}" @class(['rounded-lg px-3.5 py-2 text-sm font-semibold', 'bg-ink text-white' => ! $showHandled, 'text-muted hover:text-ink' => $showHandled])>New</a>
        <a href="{{ route('platform.leads.index', ['handled' => 1]) }}" @class(['rounded-lg px-3.5 py-2 text-sm font-semibold', 'bg-ink text-white' => $showHandled, 'text-muted hover:text-ink' => ! $showHandled])>All</a>
    </div>

    <x-card :padding="false">
        @forelse ($leads as $lead)
            <div class="flex flex-wrap items-start gap-4 border-b border-line px-6 py-4 last:border-b-0">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold">{{ $lead->school_name ?? $lead->name ?? 'Unnamed lead' }}</span>
                        <x-badge tone="brand">{{ $lead->kind->label() }}</x-badge>
                        @if ($lead->student_count)
                            <x-badge>{{ $lead->student_count }} students</x-badge>
                        @endif
                    </div>
                    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted">
                        @if ($lead->name && $lead->school_name)<span>{{ $lead->name }}</span>@endif
                        @if ($lead->email)<a href="mailto:{{ $lead->email }}" class="text-brand-600 hover:text-ink">{{ $lead->email }}</a>@endif
                        @if ($lead->phone)<a href="tel:{{ $lead->phone }}" class="text-brand-600 hover:text-ink">{{ $lead->phone }}</a>@endif
                        <span>From the {{ $lead->source === 'contact' ? 'contact page' : ($lead->source === 'hero' ? 'top of the home page' : 'bottom of the home page') }}</span>
                        <time datetime="{{ $lead->created_at->toIso8601String() }}">{{ $lead->created_at->format('d/m/Y H:i') }}</time>
                    </div>
                    @if ($lead->message)
                        <p class="m-0 mt-2 max-w-3xl text-[15px] whitespace-pre-line">{{ $lead->message }}</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('platform.leads.update', $lead) }}">
                    @csrf
                    @method('PATCH')
                    <x-button :variant="$lead->handled_at ? 'ghost' : 'secondary'" size="sm" :icon="$lead->handled_at ? 'history' : 'check'">
                        {{ $lead->handled_at ? 'Mark as new' : 'Mark handled' }}
                    </x-button>
                </form>
            </div>
        @empty
            <x-empty-state icon="inbox" title="{{ $showHandled ? 'No leads yet' : 'You’re all caught up' }}" body="New demo requests from the website will appear here." />
        @endforelse

        @if ($leads->hasPages())
            <div class="border-t border-line px-6 py-4">{{ $leads->links() }}</div>
        @endif
    </x-card>
</x-layouts.app>
