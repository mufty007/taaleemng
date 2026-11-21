<x-layouts.app title="Users & roles">
    <x-page-header kicker="System" title="Users & roles" description="Everyone who can sign in to this school, and what their role lets them do. Disabling someone keeps all their records." />

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('school.members.index') }}" @class(['rounded-full border px-3.5 py-1.5 text-sm font-semibold', 'border-ink bg-ink text-white' => ! $selectedRole, 'border-line bg-white text-muted hover:text-ink' => $selectedRole])>Everyone</a>
        @foreach (\App\Enums\SchoolRole::cases() as $role)
            <a href="{{ route('school.members.index', ['role' => $role->value]) }}" @class(['inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-semibold', 'border-ink bg-ink text-white' => $selectedRole === $role, 'border-line bg-white text-muted hover:text-ink' => $selectedRole !== $role])>
                {{ $role->label() }}
                <span @class(['rounded-full px-1.5 text-xs', 'bg-white/20' => $selectedRole === $role, 'bg-chip' => $selectedRole !== $role])>{{ $roleCounts[$role->value] }}</span>
            </a>
        @endforeach
    </div>

    <x-card :padding="false">
        <form method="GET" class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4" role="search">
            @if ($selectedRole)
                <input type="hidden" name="role" value="{{ $selectedRole->value }}">
            @endif
            <label for="search" class="sr-only">Search people</label>
            <div class="relative min-w-[220px] flex-1">
                <x-lucide-search class="pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2 text-muted" aria-hidden="true" />
                <input id="search" name="search" type="search" value="{{ $search }}" placeholder="Name, email or phone" class="field pl-10">
            </div>
            <x-button variant="secondary">Search</x-button>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[820px] border-collapse text-left text-[15px]">
                <thead>
                    <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                        <th scope="col" class="px-5 py-3 font-semibold">Person</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Role</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Last active here</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Access</th>
                        <th scope="col" class="px-5 py-3 font-semibold"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($members as $member)
                        @php
                            $active = $member->membership->status === \App\Enums\MembershipStatus::Active;
                            $memberRoles = $rolesByUser[$member->id] ?? [];
                            $staffRole = collect($memberRoles)
                                ->map(fn (string $name) => \App\Enums\SchoolRole::tryFrom($name))
                                ->first(fn (?\App\Enums\SchoolRole $role) => $role?->isStaff());
                            $portalRoles = collect($memberRoles)
                                ->map(fn (string $name) => \App\Enums\SchoolRole::tryFrom($name))
                                ->filter(fn (?\App\Enums\SchoolRole $role) => $role && ! $role->isStaff());
                        @endphp
                        <tr class="border-t border-line">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="grid size-9 flex-none place-items-center rounded-full bg-chip text-xs font-semibold">{{ $member->initials() }}</span>
                                    <span class="min-w-0">
                                        <span class="block truncate font-semibold">{{ $member->name }}</span>
                                        <span class="block truncate text-sm text-muted">{{ $member->email ?? $member->phone ?? 'Logs in with admission number' }}</span>
                                    </span>
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex flex-col gap-2">
                                    @if ($staffRole && ! $member->is(auth()->user()))
                                        <form method="POST" action="{{ route('school.members.role', $member->ulid) }}" class="flex flex-wrap items-center gap-2">
                                            @csrf
                                            <label class="sr-only" for="role-{{ $member->ulid }}">Role for {{ $member->name }}</label>
                                            <select id="role-{{ $member->ulid }}" name="role" class="field py-1.5 text-sm" onchange="this.form.requestSubmit()">
                                                @foreach ($staffRoleOptions as $value => $label)
                                                    <option value="{{ $value }}" @selected($staffRole->value === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @elseif ($staffRole)
                                        <x-badge tone="brand">{{ $staffRole->label() }}</x-badge>
                                    @endif
                                    @foreach ($portalRoles as $portalRole)
                                        <x-badge tone="neutral">{{ $portalRole->label() }}</x-badge>
                                    @endforeach
                                    @if (! $staffRole && $portalRoles->isEmpty())
                                        <span class="text-sm text-muted">No role</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-3 text-sm text-muted">{{ $member->membership->last_active_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="px-3 py-3">
                                <x-badge :tone="$active ? 'naija' : 'danger'">{{ $active ? 'Can sign in' : 'Disabled' }}</x-badge>
                            </td>
                            <td class="px-5 py-3 text-right">
                                @unless ($member->is(auth()->user()))
                                    <div class="flex justify-end gap-1">
                                        @if ($member->email && $active)
                                            <form method="POST" action="{{ route('school.members.invite', $member->ulid) }}">
                                                @csrf
                                                <x-button variant="ghost" size="sm" icon="mail" title="Email {{ $member->email }} a link to choose a password">Email sign-in link</x-button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('school.members.toggle', $member->ulid) }}">
                                            @csrf
                                            <x-button :variant="$active ? 'ghost' : 'soft'" size="sm" :icon="$active ? 'user-round-x' : 'user-round-check'">{{ $active ? 'Disable' : 'Restore' }}</x-button>
                                        </form>
                                    </div>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state icon="users" title="Nobody matches" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($members->hasPages())
            <div class="border-t border-line px-5 py-4">{{ $members->links() }}</div>
        @endif
    </x-card>
</x-layouts.app>
