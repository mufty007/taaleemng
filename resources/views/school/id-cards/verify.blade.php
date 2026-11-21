<x-layouts.school-public title="ID card check" :school="$school">
    <div class="mx-auto flex max-w-md flex-col items-center gap-5 text-center">
        @if ($person && $valid)
            <span class="grid size-16 place-items-center rounded-full bg-naija-50 text-naija"><x-lucide-badge-check class="size-9" aria-hidden="true" /></span>
            <div>
                <h1 class="m-0 text-2xl font-bold tracking-[-0.02em]">Valid {{ $kind === 'staff' ? 'staff' : 'student' }} ID card</h1>
                <p class="m-0 mt-1 text-[15px] text-muted">This card was issued by {{ $school->name }} and is current.</p>
            </div>
        @elseif ($person)
            <span class="grid size-16 place-items-center rounded-full bg-gold-50 text-gold"><x-lucide-badge-alert class="size-9" aria-hidden="true" /></span>
            <div>
                <h1 class="m-0 text-2xl font-bold tracking-[-0.02em]">This card is no longer valid</h1>
                <p class="m-0 mt-1 text-[15px] text-muted">The holder is no longer an active {{ $kind === 'staff' ? 'member of staff' : 'student' }} at {{ $school->name }}. Please contact the school.</p>
            </div>
        @else
            <span class="grid size-16 place-items-center rounded-full bg-danger-50 text-danger"><x-lucide-badge-x class="size-9" aria-hidden="true" /></span>
            <div>
                <h1 class="m-0 text-2xl font-bold tracking-[-0.02em]">We can’t find this card</h1>
                <p class="m-0 mt-1 text-[15px] text-muted">It was not issued by {{ $school->name }} through Ta’aleem. Please contact the school.</p>
            </div>
        @endif

        @if ($person)
            <x-card class="w-full text-left">
                <dl class="m-0 grid grid-cols-[auto_minmax(0,1fr)] gap-x-6 gap-y-2 text-[15px]">
                    <dt class="text-muted">Name</dt>
                    <dd class="m-0 font-semibold">{{ $person->fullName() }}</dd>
                    <dt class="text-muted">{{ $kind === 'staff' ? 'Staff no.' : 'Admission no.' }}</dt>
                    <dd class="m-0 font-mono font-semibold">{{ $kind === 'staff' ? $person->staff_no : $person->admission_no }}</dd>
                    @if ($valid && $detail)
                        <dt class="text-muted">{{ $kind === 'staff' ? 'Role' : 'Class' }}</dt>
                        <dd class="m-0 font-semibold">{{ $detail }}</dd>
                    @endif
                </dl>
            </x-card>
        @endif
    </div>
</x-layouts.school-public>
