<x-layouts.app :title="$staff->fullName()">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.staff.index') }}" class="hover:text-ink">Staff</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">{{ $staff->fullName() }}</span>
    </div>

    <x-credentials />

    <div class="flex flex-wrap items-center gap-5 rounded-3xl border border-line bg-white p-6" data-enter>
        <span class="grid size-20 flex-none place-items-center rounded-3xl bg-ink text-2xl font-bold text-white">{{ \Illuminate\Support\Str::upper(substr($staff->first_name, 0, 1).substr($staff->last_name, 0, 1)) }}</span>
        <div class="min-w-0 flex-1">
            <h1 class="m-0 text-[28px] leading-tight font-bold tracking-[-0.03em]">{{ $staff->fullName() }}</h1>
            <div class="mt-1.5 flex flex-wrap items-center gap-2 text-[15px] text-muted">
                <span>{{ $staff->designation }}</span>
                <span aria-hidden="true">·</span>
                <span class="font-mono">{{ $staff->staff_no }}</span>
                <x-badge :tone="$staff->status === \App\Enums\StaffStatus::Active ? 'naija' : 'neutral'">{{ $staff->status->label() }}</x-badge>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
        <x-card :padding="false">
            <h2 class="m-0 border-b border-line px-6 py-4 text-lg font-semibold">Teaching this session</h2>
            @if ($staff->formClasses->isEmpty() && $staff->classSubjects->isEmpty())
                <x-empty-state icon="book-open" title="No classes assigned" body="Assign subjects from each class’s page." />
            @else
                <ul class="m-0 list-none p-0">
                    @foreach ($staff->formClasses as $formClass)
                        <li class="flex items-center justify-between gap-3 border-b border-line px-6 py-3">
                            <span class="font-semibold">{{ $formClass->displayName() }}</span>
                            <x-badge tone="brand">Form teacher</x-badge>
                        </li>
                    @endforeach
                    @foreach ($staff->classSubjects->sortBy(fn ($classSubject) => sprintf('%03d', $classSubject->classArm->level->position).$classSubject->classArm->name) as $classSubject)
                        <li class="flex items-center justify-between gap-3 border-b border-line px-6 py-3 last:border-b-0">
                            <span class="font-medium">{{ $classSubject->subject->name }}</span>
                            <span class="text-sm text-muted">{{ $classSubject->classArm->displayName() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card>
            <h2 class="m-0 mb-4 text-lg font-semibold">Details</h2>
            <dl class="m-0 grid grid-cols-[auto_minmax(0,1fr)] gap-x-6 gap-y-3 text-[15px]">
                <dt class="text-muted">Phone</dt>
                <dd class="m-0 font-medium">{{ $staff->phone ?? '—' }}</dd>
                <dt class="text-muted">Email</dt>
                <dd class="m-0 truncate font-medium">{{ $staff->email ?? '—' }}</dd>
                <dt class="text-muted">Department</dt>
                <dd class="m-0 font-medium">{{ $staff->department ?? '—' }}</dd>
                <dt class="text-muted">Employment</dt>
                <dd class="m-0 font-medium">{{ $staff->employment_type->label() }}</dd>
                <dt class="text-muted">Joined</dt>
                <dd class="m-0 font-medium">{{ $staff->date_employed?->format('d/m/Y') ?? '—' }}</dd>
                <dt class="text-muted">Qualification</dt>
                <dd class="m-0 font-medium">{{ $staff->qualification ?? '—' }}</dd>
                <dt class="text-muted">Login</dt>
                <dd class="m-0 font-medium">
                    @if ($staff->user)
                        {{ $staff->user->last_login_at ? 'Last logged in '.$staff->user->last_login_at->diffForHumans() : 'Has not logged in yet' }}
                        @if ($staff->user->email && ! $staff->user->last_login_at)
                            @can('users.manage')
                                <form method="POST" action="{{ route('school.members.invite', $staff->user->ulid) }}" class="mt-2">
                                    @csrf
                                    <x-button variant="soft" size="sm" icon="mail">Email a sign-in link</x-button>
                                </form>
                            @endcan
                        @endif
                    @else
                        No login
                    @endif
                </dd>
            </dl>
        </x-card>
    </div>
</x-layouts.app>
