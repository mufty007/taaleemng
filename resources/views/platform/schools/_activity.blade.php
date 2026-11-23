<x-card :padding="false">
    <div class="border-b border-line px-5 py-4">
        <h2 class="m-0 text-base font-semibold">What’s happened in the school</h2>
        <p class="m-0 text-sm text-muted">Newest first. Anything Ta’aleem support did is marked.</p>
    </div>
    @forelse ($activity as $entry)
        <div class="flex items-start gap-3 border-b border-line px-5 py-3 last:border-b-0">
            <span class="mt-0.5 grid size-8 flex-none place-items-center rounded-full bg-chip text-[11px] font-semibold">{{ $entry->causer?->initials() ?? '·' }}</span>
            <div class="min-w-0 flex-1 text-sm">
                <span class="font-semibold">{{ $entry->causer?->name ?? 'System' }}</span>
                <span class="text-muted">{{ $entry->description }}</span>
                @if ($entry->properties?->get('support'))
                    <x-badge tone="gold" class="ml-1">Support</x-badge>
                @endif
                <div class="text-xs text-muted">{{ $entry->created_at->format('j M Y, H:i') }} · {{ $entry->ip_address ?? 'no IP' }}</div>
            </div>
        </div>
    @empty
        <x-empty-state icon="history" title="No activity yet" />
    @endforelse
    @if ($activity->hasPages())
        <div class="border-t border-line px-5 py-4">{{ $activity->links() }}</div>
    @endif
</x-card>
