<x-layouts.app title="Broadcasts">
    <x-page-header kicker="Platform console" title="Broadcasts" description="Notices from Ta’aleem to schools: maintenance, new features, reminders. They show as a banner until they end or are dismissed.">
        <x-slot:actions>
            <x-button type="button" icon="radio" x-data x-on:click="$dispatch('open-drawer', 'new-broadcast')">New broadcast</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        @forelse ($broadcasts as $broadcast)
            <div class="flex flex-wrap items-start gap-3 border-b border-line px-5 py-4 last:border-b-0">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold">{{ $broadcast->title }}</span>
                        <x-badge :tone="$broadcast->isLive() ? 'naija' : 'neutral'">{{ $broadcast->state() }}</x-badge>
                        <x-badge :tone="match ($broadcast->level->value) { 'critical' => 'danger', 'warning' => 'gold', default => 'brand' }">{{ $broadcast->level->label() }}</x-badge>
                    </div>
                    <p class="m-0 mt-1 line-clamp-2 text-sm text-muted">{{ $broadcast->body }}</p>
                    <p class="m-0 mt-1 text-xs text-muted">
                        {{ $broadcast->audience->label() }} · {{ $broadcast->school_ids ? collect($broadcast->school_ids)->map(fn ($id) => $schools[$id] ?? '?')->join(', ') : 'all schools' }}
                        · from {{ $broadcast->starts_at->format('j M, H:i') }}{{ $broadcast->ends_at ? ' to '.$broadcast->ends_at->format('j M, H:i') : '' }}
                        · {{ $broadcast->dismissed_by_count }} dismissed{{ $broadcast->email_admins ? ' · emailed admins' : '' }}
                    </p>
                </div>
                @if ($broadcast->state() !== 'Ended')
                    <form method="POST" action="{{ route('platform.broadcasts.end', $broadcast) }}">
                        @csrf
                        <x-button variant="secondary" size="sm" icon="square">End now</x-button>
                    </form>
                @endif
            </div>
        @empty
            <x-empty-state icon="radio" title="No broadcasts yet" body="Tell every school about planned maintenance or a new feature." />
        @endforelse
        @if ($broadcasts->hasPages())
            <div class="border-t border-line px-5 py-4">{{ $broadcasts->links() }}</div>
        @endif
    </x-card>

    <x-drawer name="new-broadcast" title="New broadcast" description="Shown at the top of every page for the people you choose." :show="$errors->broadcast->isNotEmpty()" width="sm:max-w-lg">
        <form method="POST" action="{{ route('platform.broadcasts.store') }}" class="flex flex-col gap-4">
            @csrf
            <x-field label="Title" name="title" bag="broadcast" placeholder="Planned maintenance on Saturday" required />
            <div class="flex flex-col gap-1.5">
                <label for="body" class="text-sm font-semibold">Message</label>
                <textarea id="body" name="body" rows="4" class="field" required>{{ old('body') }}</textarea>
                @error('body', 'broadcast')<p class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-select-field label="Level" name="level" bag="broadcast" :options="collect($levels)->mapWithKeys(fn ($level) => [$level->value => $level->label()])->all()" />
                <x-select-field label="Who sees it" name="audience" bag="broadcast" :options="collect($audiences)->mapWithKeys(fn ($audience) => [$audience->value => $audience->label()])->all()" />
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="school_ids" class="text-sm font-semibold">Schools <span class="font-normal text-muted">(none chosen = all)</span></label>
                <select id="school_ids" name="school_ids[]" multiple size="5" class="field">
                    @foreach ($schools as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-field label="Starts" name="starts_at" type="datetime-local" bag="broadcast" hint="Blank = now" />
                <x-field label="Ends" name="ends_at" type="datetime-local" bag="broadcast" hint="Blank = until you end it" />
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="email_admins" value="0">
                <input type="checkbox" name="email_admins" value="1" class="size-4 rounded border-line text-brand-600"> Also email every school admin
            </label>
            <x-button variant="brand" icon="send">Publish</x-button>
        </form>
    </x-drawer>
</x-layouts.app>
