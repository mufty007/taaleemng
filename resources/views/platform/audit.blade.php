<x-layouts.app title="Audit log">
    <x-page-header kicker="Platform console" title="Audit log" description="Everything super admins have done: settings, school changes, viewing as someone, and changes made inside schools in support mode." />

    <x-card :padding="false">
        <form method="GET" class="flex gap-2 border-b border-line px-4 py-4 sm:px-5" role="search">
            <label for="search" class="sr-only">Search the log</label>
            <div class="relative flex-1">
                <x-lucide-search class="pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2 text-muted" aria-hidden="true" />
                <input id="search" name="search" type="search" value="{{ $search }}" placeholder="e.g. viewed, trial, Paystack" class="field py-2.5 pl-10">
            </div>
            <x-button variant="secondary">Search</x-button>
        </form>

        @forelse ($entries as $entry)
            <div class="flex items-start gap-3 border-b border-line px-4 py-3 last:border-b-0 sm:px-5">
                <span class="mt-0.5 grid size-8 flex-none place-items-center rounded-full bg-chip text-[11px] font-semibold">{{ $entry->causer?->initials() ?? '·' }}</span>
                <div class="min-w-0 flex-1 text-sm">
                    <span class="font-semibold">{{ $entry->causer?->name ?? 'System' }}</span>
                    <span class="text-muted">{{ $entry->description }}</span>
                    @php
                        $schoolName = $schools[$entry->school_id ?? $entry->properties?->get('school_id')] ?? null;
                    @endphp
                    <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-muted">
                        @if ($entry->log_name === 'platform')
                            <x-badge tone="ink">Console</x-badge>
                        @else
                            <x-badge tone="gold">In school</x-badge>
                        @endif
                        @if ($schoolName)<span>{{ $schoolName }}</span> ·@endif
                        <span>{{ $entry->created_at->format('j M Y, H:i') }}</span> · <span>{{ $entry->ip_address ?? 'no IP' }}</span>
                    </div>
                </div>
            </div>
        @empty
            <x-empty-state icon="scroll-text" title="Nothing logged yet" />
        @endforelse

        @if ($entries->hasPages())
            <div class="border-t border-line px-5 py-4">{{ $entries->links() }}</div>
        @endif
    </x-card>
</x-layouts.app>
