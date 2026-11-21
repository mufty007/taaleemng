{{-- This term's key dates, with how far through it we are. --}}
@if ($term)
    @php
        $length = max(1, $term->starts_on->diffInDays($term->ends_on));
        $gone = (int) min(100, max(0, round($term->starts_on->diffInDays(now(), false) / $length * 100)));
    @endphp
    <x-card>
        <div class="flex items-start justify-between gap-3">
            <div>
                <div class="kicker">This term</div>
                <h2 class="m-0 mt-1 text-lg font-semibold">{{ $term->name }} · {{ $term->academicSession->name }}</h2>
            </div>
            @if ($week = $term->weekOf(now()))
                <x-badge tone="brand">Week {{ $week }}</x-badge>
            @endif
        </div>
        <div class="mt-4 h-2 overflow-hidden rounded-full bg-chip" role="progressbar" aria-valuenow="{{ $gone }}" aria-valuemin="0" aria-valuemax="100" aria-label="How far through the term">
            <div class="h-full rounded-full bg-brand" style="width: {{ $gone }}%"></div>
        </div>
        <dl class="m-0 mt-4 grid grid-cols-[auto_minmax(0,1fr)] gap-x-6 gap-y-2 text-[15px]">
            <dt class="text-muted">Resumed</dt>
            <dd class="m-0 font-medium">{{ $term->starts_on->format('D j M Y') }}</dd>
            @if ($term->mid_term_starts_on)
                <dt class="text-muted">Mid-term break</dt>
                <dd class="m-0 font-medium">{{ $term->mid_term_starts_on->format('j M') }} – {{ $term->mid_term_ends_on?->format('j M') }}</dd>
            @endif
            <dt class="text-muted">Vacation</dt>
            <dd class="m-0 font-medium">{{ $term->ends_on->format('D j M Y') }} <span class="text-sm text-muted">({{ (int) max(0, now()->diffInDays($term->ends_on, false)) }} days)</span></dd>
            @if ($term->next_term_begins_on)
                <dt class="text-muted">Next term begins</dt>
                <dd class="m-0 font-medium">{{ $term->next_term_begins_on->format('D j M Y') }}</dd>
            @endif
        </dl>
    </x-card>
@endif
