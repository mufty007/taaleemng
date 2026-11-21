{{-- Back of a CR80 ID card. Expects $card, $school and $kind. --}}
<div class="relative flex h-[54mm] w-[85.6mm] flex-none flex-col overflow-hidden rounded-[3mm] border border-line bg-white text-ink">
    <div class="h-[2mm] bg-brand-600" aria-hidden="true"></div>
    <div class="flex-1 px-[4mm] pt-[2.5mm] text-[7px] leading-[1.5]">
        <p class="m-0 font-bold">This card belongs to {{ $school->name }}.</p>
        <p class="m-0 mt-[0.8mm] text-muted">{{ $kind === 'staff' ? 'Wear it at all times while on duty.' : 'Carry it at all times in school and show it when asked.' }} If found, please return it to the school:</p>
        <p class="m-0 mt-[0.8mm] font-semibold">{{ collect([$school->address, $school->lga, $school->state])->filter()->join(', ') ?: $school->name }}</p>
        @if ($school->phone || $school->email)
            <p class="m-0 font-semibold">{{ collect([$school->phone, $school->email])->filter()->join(' · ') }}</p>
        @endif
        @if ($card['emergency'])
            <p class="m-0 mt-[1.2mm] rounded-[1mm] bg-brand-50 px-[1.5mm] py-[0.6mm] text-brand-700">In an emergency, call <span class="font-bold">{{ $card['emergency'] }}</span></p>
        @endif
    </div>
    <div class="flex items-end justify-between gap-[3mm] px-[4mm] pb-[3mm]">
        <span class="w-[32mm] border-t border-dashed border-ink/40 pt-[0.5mm] text-center text-[6px] text-muted">{{ $kind === 'staff' ? 'Proprietor / Principal' : 'Principal / Head teacher' }}</span>
        <img src="{{ asset('images/taaleem-logo.png') }}" alt="{{ config('app.name') }}" class="h-[3mm] w-auto">
    </div>
</div>
