@if ($recentActivity->isNotEmpty())
    <x-card :padding="false">
        <div class="flex items-center justify-between border-b border-line px-5 py-4">
            <h2 class="m-0 text-base font-semibold">Recent activity</h2>
            <a href="{{ route('school.activity.index') }}" class="text-sm font-semibold text-brand-600 hover:text-ink">View all</a>
        </div>
        <ul class="m-0 list-none p-0">
            @foreach ($recentActivity as $activity)
                <li class="flex items-start gap-3 border-b border-line px-5 py-3 last:border-b-0">
                    <span class="mt-0.5 grid size-8 flex-none place-items-center rounded-full bg-chip text-[11px] font-semibold">{{ $activity->causer?->initials() ?? '·' }}</span>
                    <div class="min-w-0 flex-1 text-sm">
                        <span class="font-semibold">{{ $activity->causer?->name ?? 'System' }}</span>
                        <span class="text-muted">{{ $activity->description }}</span>
                        <div class="text-xs text-muted">{{ $activity->created_at->diffForHumans() }}</div>
                    </div>
                </li>
            @endforeach
        </ul>
    </x-card>
@endif
