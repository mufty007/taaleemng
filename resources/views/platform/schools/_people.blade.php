<x-card :padding="false">
    <div class="flex flex-col gap-3 border-b border-line px-4 py-4 sm:px-5">
        <nav class="-mx-1 flex gap-1 overflow-x-auto px-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" aria-label="Who">
            @foreach ($peopleFilters as $value => $label)
                <a href="{{ route('platform.schools.show', array_filter(['school' => $school, 'who' => $value, 'people' => $peopleSearch ?: null])) }}#people"
                   @class(['inline-flex flex-none items-center rounded-full border px-3.5 py-1.5 text-sm font-semibold transition', 'border-ink bg-ink text-white' => $peopleFilter === $value, 'border-line bg-white text-muted hover:text-ink' => $peopleFilter !== $value])>{{ $label }}</a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('platform.schools.show', $school) }}#people" class="flex gap-2" role="search">
            <input type="hidden" name="who" value="{{ $peopleFilter }}">
            <label for="people-search" class="sr-only">Search people</label>
            <div class="relative flex-1">
                <x-lucide-search class="pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2 text-muted" aria-hidden="true" />
                <input id="people-search" name="people" type="search" value="{{ $peopleSearch }}" placeholder="Name, email or phone" class="field py-2.5 pl-10">
            </div>
            <x-button variant="secondary">Search</x-button>
        </form>
    </div>

    @if ($people->isEmpty())
        <x-empty-state icon="users" title="No one found" body="Try another name, or look under Everyone." />
    @else
        <ul class="m-0 list-none p-0">
            @foreach ($people as $person)
                @php
                    $roles = $peopleRoles->get($person->id, collect());
                    $disabled = $person->membership->status !== \App\Enums\MembershipStatus::Active;
                @endphp
                <li class="flex flex-wrap items-center gap-3 border-b border-line px-4 py-3 last:border-b-0 sm:flex-nowrap sm:px-5">
                    <span class="grid size-10 flex-none place-items-center rounded-full bg-sky-100 text-xs font-semibold text-ink">{{ $person->initials() }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="truncate font-semibold">{{ $person->name }}</span>
                            @foreach ($roles as $role)
                                <x-badge :tone="$role->isStaff() ? 'brand' : 'neutral'">{{ $role->label() }}</x-badge>
                            @endforeach
                            @if ($disabled)
                                <x-badge tone="danger">Access off</x-badge>
                            @endif
                        </div>
                        <div class="truncate text-sm text-muted">
                            {{ $person->email ?? \App\Models\User::displayPhone($person->phone) ?? 'No email or phone' }}
                            · {{ $person->last_login_at ? 'signed in '.$person->last_login_at->diffForHumans() : 'never signed in' }}
                            @if ($person->two_factor_confirmed_at) · <span class="text-naija">2-step on</span>@endif
                        </div>
                    </div>
                    <div class="flex flex-none gap-2">
                        @if ($person->email)
                            <form method="POST" action="{{ route('platform.schools.members.sign-in-link', [$school, $person]) }}">
                                @csrf
                                <x-button variant="ghost" size="sm" icon="mail">{{ $person->last_login_at ? 'Reset link' : 'Invite again' }}</x-button>
                            </form>
                        @endif
                        @unless ($person->is_platform_admin || $disabled)
                            <form method="POST" action="{{ route('platform.view-as.start', [$school, $person]) }}">
                                @csrf
                                <x-button variant="soft" size="sm" icon="eye">View as</x-button>
                            </form>
                        @endunless
                    </div>
                </li>
            @endforeach
        </ul>
        @if ($people->hasPages())
            <div class="border-t border-line px-5 py-4">{{ $people->links() }}</div>
        @endif
    @endif
</x-card>
