<x-layouts.app title="My children">
    <x-page-header
        :kicker="$currentSchool->name"
        :title="'Welcome, '.($guardian->title ? $guardian->title.' ' : '').$guardian->last_name"
        :description="$children->count() === 1 ? 'Here is how your child is doing.' : 'Here is how your '.$children->count().' children are doing.'"
    />

    @if ($term)
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-2xl bg-ink px-6 py-4 text-white">
            <span class="flex items-center gap-2 font-semibold"><x-lucide-calendar-range class="size-5 text-brand-200" aria-hidden="true" /> {{ $term->name }} · {{ $term->academicSession->name }}</span>
            @if ($term->mid_term_starts_on && now()->lt($term->mid_term_starts_on))
                <span class="text-sm text-ink-300">Mid-term break {{ $term->mid_term_starts_on->format('j M') }} – {{ $term->mid_term_ends_on?->format('j M') }}</span>
            @endif
            <span class="text-sm text-ink-300">School closes {{ $term->ends_on->format('D j M') }}</span>
            @if ($term->next_term_begins_on)
                <span class="text-sm text-ink-300">Next term begins {{ $term->next_term_begins_on->format('j M Y') }}</span>
            @endif
        </div>
    @endif

    @include('school.announcements._latest')

    <div class="grid grid-cols-1 gap-5 md:grid-cols-2" data-stagger>
        @foreach ($children as $child)
            @php($arm = $child->currentEnrolment?->classArm)
            <article class="flex flex-col gap-5 rounded-3xl border border-line bg-white p-6">
                <div class="flex items-center gap-4">
                    <x-avatar :person="$child" class="size-16 rounded-2xl text-xl" />
                    <div class="min-w-0">
                        <h2 class="m-0 text-xl font-bold tracking-[-0.02em]">{{ $child->fullName() }}</h2>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted">
                            @if ($arm)
                                <x-badge tone="brand">{{ $arm->displayName() }}</x-badge>
                            @endif
                            <span class="font-mono">{{ $child->admission_no }}</span>
                        </div>
                    </div>
                </div>

                <dl class="m-0 grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl bg-canvas p-3">
                        <dt class="text-muted">Form teacher</dt>
                        <dd class="m-0 mt-0.5 font-semibold">{{ $arm?->formTeacher?->fullName() ?? 'Not assigned' }}</dd>
                    </div>
                    <div class="rounded-xl bg-canvas p-3">
                        <dt class="text-muted">Age</dt>
                        <dd class="m-0 mt-0.5 font-semibold">{{ $child->age() ? $child->age().' years' : '—' }}</dd>
                    </div>
                </dl>

                @php($balance = $balances[$child->id] ?? 0)
                <div class="grid grid-cols-3 gap-2 text-center text-xs">
                    <a href="{{ route('school.portal.child', $child) }}#results" class="flex flex-col items-center gap-1.5 rounded-xl border border-line px-2 py-3 text-muted hover:border-brand hover:text-ink">
                        <x-lucide-file-text class="size-5" aria-hidden="true" /> Results
                    </a>
                    <a href="{{ route('school.portal.child', $child) }}#fees" @class(['flex flex-col items-center gap-1 rounded-xl border px-2 py-3 hover:border-brand', 'border-danger/30 bg-danger-50 text-danger' => $balance > 0, 'border-line text-muted' => $balance <= 0])>
                        <x-lucide-receipt class="size-5" aria-hidden="true" />
                        <span>{{ $balance > 0 ? 'Fees owed' : 'School fees' }}</span>
                        <span class="text-sm font-bold">{{ $balance > 0 ? \App\Support\Money::format($balance) : 'Paid up' }}</span>
                    </a>
                    @php($register = $attendance[$child->currentEnrolment?->id] ?? null)
                    <a href="{{ route('school.portal.child', $child) }}#attendance" class="flex flex-col items-center gap-1 rounded-xl border border-line px-2 py-3 text-muted hover:border-brand hover:text-ink">
                        <x-lucide-clipboard-check class="size-5" aria-hidden="true" />
                        <span>Attendance</span>
                        <span class="text-sm font-bold text-ink">{{ $register && $register['rate'] !== null ? $register['rate'].'%' : '—' }}</span>
                    </a>
                </div>

                <x-button variant="secondary" :href="route('school.portal.child', $child)" class="mt-auto">See {{ $child->first_name }}’s class and teachers</x-button>
            </article>
        @endforeach
    </div>
</x-layouts.app>
