<x-layouts.app :title="$person->name">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('platform.users.index') }}" class="hover:text-ink">Users</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="truncate text-ink">{{ $person->name }}</span>
    </div>

    <div class="flex flex-wrap items-center gap-4 rounded-3xl border border-line bg-white p-5 sm:p-6">
        <span class="grid size-16 flex-none place-items-center rounded-2xl bg-sky-100 text-xl font-bold text-ink">{{ $person->initials() }}</span>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="m-0 text-2xl font-bold tracking-[-0.02em]">{{ $person->name }}</h1>
                @if ($person->is_platform_admin)<x-badge tone="ink">Super admin</x-badge>@endif
            </div>
            <p class="m-0 mt-1 text-sm text-muted">{{ $person->email ?? 'No email' }} · {{ $person->phone ? \App\Models\User::displayPhone($person->phone) : 'No phone' }} · joined {{ $person->created_at->format('j M Y') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <x-card :padding="false">
            <h2 class="m-0 border-b border-line px-5 py-4 text-base font-semibold">Schools</h2>
            @forelse ($person->schools as $school)
                @php
                    $roles = $rolesBySchool->get($school->id, collect());
                    $active = $school->membership->status === \App\Enums\MembershipStatus::Active;
                @endphp
                <div class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-3 last:border-b-0">
                    <x-school-crest :school="$school" class="size-10 flex-none rounded-xl text-xs" />
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('platform.schools.show', $school) }}" class="font-semibold hover:text-brand-600">{{ $school->name }}</a>
                        <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                            @foreach ($roles as $role)<x-badge :tone="$role->isStaff() ? 'brand' : 'neutral'">{{ $role->label() }}</x-badge>@endforeach
                            @unless ($active)<x-badge tone="danger">Access off</x-badge>@endunless
                            <span class="text-xs text-muted">{{ $school->membership->last_active_at ? 'active '.$school->membership->last_active_at->diffForHumans() : 'never active' }}</span>
                        </div>
                    </div>
                    @if ($active && ! $person->is_platform_admin)
                        <form method="POST" action="{{ route('platform.view-as.start', [$school, $person]) }}">
                            @csrf
                            <x-button variant="soft" size="sm" icon="eye">View as</x-button>
                        </form>
                    @endif
                </div>
            @empty
                <x-empty-state icon="school" title="Not in any school" body="{{ $person->is_platform_admin ? 'Super admins reach every school from the console.' : 'They have a login but no school yet.' }}" />
            @endforelse
        </x-card>

        <aside class="flex min-w-0 flex-col gap-6">
            <x-card>
                <h2 class="m-0 text-base font-semibold">Signing in</h2>
                <dl class="m-0 mt-4 grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-3 text-sm">
                    <dt class="text-muted">Last sign-in</dt>
                    <dd class="m-0 font-medium">{{ $person->last_login_at?->diffForHumans() ?? 'Never' }}</dd>
                    <dt class="text-muted">Two-step</dt>
                    <dd class="m-0 font-medium">{{ $person->two_factor_confirmed_at ? 'On' : 'Off' }}</dd>
                </dl>
                <div class="mt-5 flex flex-col gap-2">
                    @if ($person->email)
                        <form method="POST" action="{{ route('platform.users.password-reset', $person) }}">
                            @csrf
                            <x-button variant="secondary" icon="key-round" class="w-full">Email a password-reset link</x-button>
                        </form>
                    @endif
                    @if ($person->two_factor_confirmed_at && ! $person->is(auth()->user()))
                        <form method="POST" action="{{ route('platform.users.two-factor', $person) }}" data-confirm="Turn off two-step sign-in for {{ $person->name }}? Only do this if you have confirmed it is really them, for example by phone." data-confirm-title="Turn off two-step sign-in" data-confirm-action="Turn it off">
                            @csrf
                            @method('DELETE')
                            <x-button variant="secondary" icon="shield-off" class="w-full">Turn off two-step sign-in</x-button>
                        </form>
                    @endif
                </div>
            </x-card>

            @if ($person->is_platform_admin && ! $person->is(auth()->user()))
                <x-card>
                    <h2 class="m-0 text-base font-semibold">Super admin</h2>
                    <p class="m-0 mt-1 text-sm text-muted">They can see and change every school and the platform settings.</p>
                    <form method="POST" action="{{ route('platform.users.admins.destroy', $person) }}" class="mt-4" data-confirm="Remove {{ $person->name }} as a super admin? They keep any school logins they have." data-confirm-title="Remove super admin" data-confirm-action="Remove">
                        @csrf
                        @method('DELETE')
                        <x-button variant="danger" icon="user-minus">Remove super admin</x-button>
                    </form>
                </x-card>
            @endif
        </aside>
    </div>
</x-layouts.app>
