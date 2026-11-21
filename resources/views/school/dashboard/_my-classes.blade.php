<x-card :padding="false">
    <div class="border-b border-line px-5 py-4">
        <h2 class="m-0 text-base font-semibold">My classes</h2>
        <p class="m-0 text-sm text-muted">Open a class to take the register, set classwork or see its students.</p>
    </div>
    <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2">
        @foreach ($formClasses as $formClass)
            <a href="{{ route('school.classes.show', $formClass) }}" class="flex flex-col gap-1.5 rounded-2xl border border-brand-200 bg-brand-50 p-4 transition hover:border-brand">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-lg font-bold">{{ $formClass->displayName() }}</span>
                    <x-badge tone="brand">Form teacher</x-badge>
                </div>
                <span class="text-sm text-muted">{{ $formClass->enrolments_count }} students</span>
            </a>
        @endforeach
        @foreach ($myClasses as $row)
            <a href="{{ route('school.classes.show', $row['arm']) }}" class="flex flex-col gap-1.5 rounded-2xl border border-line p-4 transition hover:border-brand">
                <span class="text-lg font-bold">{{ $row['arm']->displayName() }}</span>
                <span class="text-sm text-muted">{{ implode(', ', $row['subjects']) }}</span>
            </a>
        @endforeach
    </div>
</x-card>
