<x-layouts.school-public title="Admissions" :school="$school">
    <div class="flex flex-col items-center gap-4 rounded-2xl border border-line bg-white px-6 py-12 text-center shadow-card">
        <span class="grid size-14 place-items-center rounded-2xl bg-chip text-muted"><x-lucide-calendar-clock class="size-7" aria-hidden="true" /></span>
        <h1 class="m-0 text-[28px] leading-tight font-bold tracking-[-0.03em]">Applications are closed for now</h1>
        <p class="m-0 max-w-md text-[15px] text-muted">{{ $school->name }} is not taking online applications at the moment.{{ $school->phone ? ' Call '.$school->phone.' to ask about places.' : '' }}</p>
    </div>
</x-layouts.school-public>
