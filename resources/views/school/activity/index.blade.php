<x-layouts.app title="Activity log">
    <x-page-header kicker="System" title="Activity log" description="Who signed in, and who changed what, in this school." />

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('school.activity.index') }}" @class(['rounded-full border px-3.5 py-1.5 text-sm font-semibold', 'border-ink bg-ink text-white' => ! $selectedLog, 'border-line bg-white text-muted hover:text-ink' => $selectedLog])>Everything</a>
        @foreach ($logs as $value => $label)
            <a href="{{ route('school.activity.index', ['log' => $value]) }}" @class(['rounded-full border px-3.5 py-1.5 text-sm font-semibold', 'border-ink bg-ink text-white' => $selectedLog === $value, 'border-line bg-white text-muted hover:text-ink' => $selectedLog !== $value])>{{ $label }}</a>
        @endforeach
    </div>

    <x-card :padding="false">
        @if ($activities->isEmpty())
            <x-empty-state icon="history" title="Nothing recorded yet" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] border-collapse text-left text-[15px]">
                    <thead>
                        <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                            <th scope="col" class="px-5 py-3 font-semibold">When</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Who</th>
                            <th scope="col" class="px-3 py-3 font-semibold">What</th>
                            <th scope="col" class="px-5 py-3 font-semibold">From</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($activities as $activity)
                            <tr class="border-t border-line">
                                <td class="px-5 py-3 text-sm whitespace-nowrap">
                                    <time datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->format('d/m/Y H:i') }}</time>
                                    <span class="block text-xs text-muted">{{ $activity->created_at->diffForHumans() }}</span>
                                </td>
                                <td class="px-3 py-3 font-medium">{{ $activity->causer?->name ?? 'System' }}</td>
                                <td class="px-3 py-3">
                                    <x-badge>{{ $logs[$activity->log_name] ?? ucfirst((string) $activity->log_name) }}</x-badge>
                                    <span class="ml-1">{{ $activity->description }}</span>
                                </td>
                                <td class="px-5 py-3 font-mono text-sm text-muted">{{ $activity->ip_address ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($activities->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $activities->links() }}</div>
            @endif
        @endif
    </x-card>
</x-layouts.app>
