<x-layouts.app title="Users">
    <x-page-header kicker="Platform console" title="Users" :description="number_format($total).' people can sign in to Ta’aleem. Find anyone to see their schools and help them get back in.'">
        <x-slot:actions>
            <x-button icon="shield-plus" x-data x-on:click="$dispatch('open-drawer', 'new-admin')" type="button">Add super admin</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        <div class="flex flex-col gap-3 border-b border-line px-4 py-4 sm:px-5">
            <nav class="flex gap-1" aria-label="Who">
                @foreach (['' => ['Everyone', $total], 'admins' => ['Super admins', $adminCount]] as $value => [$text, $count])
                    @php
                        $current = ($admins ? 'admins' : '') === $value;
                    @endphp
                    <a href="{{ route('platform.users.index', array_filter(['who' => $value ?: null, 'search' => $search ?: null])) }}" @class(['inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-sm font-semibold', 'border-ink bg-ink text-white' => $current, 'border-line bg-white text-muted hover:text-ink' => ! $current])>
                        {{ $text }} <span @class(['rounded-full px-1.5 text-xs', 'bg-white/20' => $current, 'bg-chip' => ! $current])>{{ number_format($count) }}</span>
                    </a>
                @endforeach
            </nav>
            <form method="GET" class="flex gap-2" role="search">
                @if ($admins)<input type="hidden" name="who" value="admins">@endif
                <label for="search" class="sr-only">Search people</label>
                <div class="relative flex-1">
                    <x-lucide-search class="pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2 text-muted" aria-hidden="true" />
                    <input id="search" name="search" type="search" value="{{ $search }}" placeholder="Name, email or phone number" class="field py-2.5 pl-10" autofocus>
                </div>
                <x-button variant="secondary">Search</x-button>
            </form>
        </div>

        @if ($users->isEmpty())
            <x-empty-state icon="search-x" title="No one found" body="Try a phone number like 0803 123 4567, or part of an email address." />
        @else
            <ul class="m-0 list-none p-0">
                @foreach ($users as $person)
                    <li class="border-b border-line last:border-b-0">
                        <a href="{{ route('platform.users.show', $person) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-canvas sm:px-5">
                            <span class="grid size-10 flex-none place-items-center rounded-full bg-sky-100 text-xs font-semibold text-ink">{{ $person->initials() }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-1.5">
                                    <span class="truncate font-semibold">{{ $person->name }}</span>
                                    @if ($person->is_platform_admin)<x-badge tone="ink">Super admin</x-badge>@endif
                                    @if ($person->two_factor_confirmed_at)<x-badge tone="naija">2-step on</x-badge>@endif
                                </span>
                                <span class="block truncate text-sm text-muted">{{ $person->email ?? \App\Models\User::displayPhone($person->phone) }} · {{ $person->active_schools_count }} {{ str('school')->plural($person->active_schools_count) }} · {{ $person->last_login_at ? 'signed in '.$person->last_login_at->diffForHumans() : 'never signed in' }}</span>
                            </span>
                            <x-lucide-chevron-right class="size-4 flex-none text-muted" aria-hidden="true" />
                        </a>
                    </li>
                @endforeach
            </ul>
            @if ($users->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $users->links() }}</div>
            @endif
        @endif
    </x-card>

    <x-drawer name="new-admin" title="Add a super admin" description="They can see and change every school, and the platform settings. Add only people you trust with that." :show="$errors->admin->isNotEmpty()" width="sm:max-w-lg">
        <form method="POST" action="{{ route('platform.users.admins.store') }}" class="flex flex-col gap-4">
            @csrf
            <x-field label="Full name" name="name" bag="admin" required />
            <x-field label="Email" name="email" type="email" bag="admin" hint="If they already use Ta’aleem with this email, we upgrade that login. Either way they get a link to set a password." required />
            <x-button variant="brand" icon="check">Make super admin</x-button>
        </form>
    </x-drawer>
</x-layouts.app>
