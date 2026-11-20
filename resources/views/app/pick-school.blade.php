<x-layouts.app title="Choose a school">
    <x-page-header kicker="Your schools" title="Which school are you working in?" description="You have access to more than one school. You can switch at any time from the menu." />

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2" data-stagger>
        @foreach ($schools as $school)
            <a href="{{ route('school.dashboard', $school) }}" class="group flex items-center gap-4 rounded-2xl border border-line bg-white p-5 transition hover:border-brand">
                <span class="grid size-12 flex-none place-items-center rounded-xl bg-brand-50 text-base font-bold text-brand-600">{{ $school->initials() }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-lg font-semibold">{{ $school->name }}</span>
                    <span class="block truncate text-sm text-muted">{{ implode(', ', $rolesBySchool[$school->id] ?? ['Member']) }}{{ $school->state ? ' · '.$school->state : '' }}</span>
                </span>
                @unless ($school->allowsAccess())
                    <x-badge tone="danger">{{ $school->status->label() }}</x-badge>
                @endunless
                <x-lucide-arrow-right class="size-5 text-muted transition group-hover:translate-x-0.5 group-hover:text-brand-600" aria-hidden="true" />
            </a>
        @endforeach
    </div>
</x-layouts.app>
