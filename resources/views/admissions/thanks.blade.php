<x-layouts.school-public title="Application received" :school="$school">
    <div class="flex flex-col items-center gap-5 rounded-2xl border border-line bg-white px-6 py-10 text-center shadow-card">
        <span class="grid size-14 place-items-center rounded-2xl bg-naija-50 text-naija-700"><x-lucide-circle-check-big class="size-7" aria-hidden="true" /></span>
        <div class="flex flex-col gap-2">
            <h1 class="m-0 text-[28px] leading-tight font-bold tracking-[-0.03em]">Application received</h1>
            <p class="m-0 text-[15px] text-muted">Thank you. {{ $school->name }} has {{ $application['child'] }}’s application for {{ $application['class'] }}.</p>
        </div>
        <div class="rounded-2xl bg-canvas px-6 py-4">
            <div class="text-xs font-semibold tracking-[0.12em] text-muted uppercase">Application number</div>
            <div class="mt-1 font-mono text-2xl font-bold">{{ $application['number'] }}</div>
        </div>
        <p class="m-0 max-w-md text-[15px] text-muted">Keep this number. The school will contact you on {{ $application['phone'] }} about the next steps.</p>
        <x-button variant="secondary" :href="route('apply.show')">Apply for another child</x-button>
    </div>
</x-layouts.school-public>
