<div class="flex flex-col gap-6 xl:sticky xl:top-24">
    <x-card>
        <h2 class="m-0 text-base font-semibold">Details</h2>
        <dl class="m-0 mt-4 grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-3 text-sm">
            <dt class="text-muted">Web address</dt>
            <dd class="m-0 truncate font-medium">/app/{{ $school->slug }}</dd>
            <dt class="text-muted">School code</dt>
            <dd class="m-0 font-mono font-medium">{{ $school->short_code }}</dd>
            <dt class="text-muted">Phone</dt>
            <dd class="m-0 font-medium">{{ $school->phone ? \App\Models\User::displayPhone($school->phone) : '—' }}</dd>
            <dt class="text-muted">Email</dt>
            <dd class="m-0 truncate font-medium">{{ $school->email ?? '—' }}</dd>
            <dt class="text-muted">Address</dt>
            <dd class="m-0 font-medium">{{ $school->address ?: '—' }}{{ $school->lga ? ', '.$school->lga : '' }}</dd>
            <dt class="text-muted">This term</dt>
            <dd class="m-0 font-medium">{{ $currentTerm ? $currentTerm->name.' · '.$currentTerm->academicSession->name : 'Not set up yet' }}</dd>
            <dt class="text-muted">Last active</dt>
            <dd class="m-0 font-medium">{{ $school->last_active_at?->diffForHumans() ?? 'Never' }}</dd>
            <dt class="text-muted">Online fees</dt>
            <dd class="m-0">
                @if ($payout['connected'])
                    <x-badge tone="naija">Connected</x-badge>
                    <span class="mt-1 block text-xs text-muted">{{ $payout['bank'] }}{{ $payout['account'] ? ' · •••• '.substr((string) $payout['account'], -4) : '' }}</span>
                @else
                    <x-badge tone="gold">No payout account</x-badge>
                @endif
            </dd>
        </dl>
    </x-card>

    <x-card :padding="false">
        <h2 class="m-0 border-b border-line px-5 py-4 text-base font-semibold">School admins</h2>
        @forelse ($admins as $admin)
            <div class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-b-0">
                <span class="grid size-9 flex-none place-items-center rounded-full bg-sky-100 text-xs font-semibold text-ink">{{ $admin->initials() }}</span>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-semibold">{{ $admin->name }}</div>
                    <div class="truncate text-xs text-muted">{{ $admin->last_login_at ? 'Signed in '.$admin->last_login_at->diffForHumans() : 'Never signed in' }}</div>
                </div>
                @unless ($admin->is_platform_admin)
                    <form method="POST" action="{{ route('platform.view-as.start', [$school, $admin]) }}">
                        @csrf
                        <button type="submit" class="grid size-8 place-items-center rounded-lg text-muted hover:bg-chip hover:text-ink" title="View as {{ $admin->name }}" aria-label="View as {{ $admin->name }}"><x-lucide-eye class="size-4" aria-hidden="true" /></button>
                    </form>
                @endunless
            </div>
        @empty
            <p class="m-0 px-5 py-4 text-sm text-muted">No school admin yet.</p>
        @endforelse
    </x-card>
</div>
