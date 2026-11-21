{{-- Front of a CR80 ID card (85.6 × 54 mm). Expects $card, $school, $session and $kind. --}}
<div class="relative flex h-[54mm] w-[85.6mm] flex-none flex-col overflow-hidden rounded-[3mm] border border-line bg-white text-ink">
    <div class="flex items-center gap-[2mm] bg-brand-600 px-[3mm] py-[1.8mm] text-white">
        <span class="grid size-[9.5mm] flex-none place-items-center overflow-hidden rounded-[1.6mm] bg-white p-[0.6mm]">
            <x-school-crest :school="$school" class="size-full rounded-[1.2mm] text-[9px]" />
        </span>
        <div class="min-w-0 flex-1">
            <div class="truncate text-[9.5px] leading-tight font-bold uppercase">{{ $school->name }}</div>
            <div class="truncate text-[6.5px] text-white/80">{{ $school->motto ?: collect([$school->lga, $school->state])->filter()->join(', ') }}</div>
        </div>
        <span class="flex-none rounded-[1mm] bg-white px-[1.5mm] py-[0.4mm] text-[6.5px] font-bold tracking-[0.12em] text-brand-700 uppercase">{{ $kind === 'staff' ? 'Staff' : 'Student' }}</span>
    </div>

    <div class="flex flex-1 gap-[2.5mm] px-[3mm] pt-[2.5mm]">
        <x-avatar :person="$card['person']" class="h-[25mm] w-[20mm] rounded-[1.5mm] border border-line text-[15px]" />
        <div class="min-w-0 flex-1">
            <div class="text-[10.5px] leading-tight font-bold uppercase">{{ $card['name'] }}</div>
            <div class="mt-[0.4mm] text-[7px] font-semibold text-muted">{{ $card['role'] }}</div>
            <div class="mt-[0.6mm] font-mono text-[8.5px] font-bold text-brand-700">{{ $card['number'] }}</div>
            <dl class="m-0 mt-[1.2mm] grid grid-cols-[auto_minmax(0,1fr)] gap-x-[1.5mm] text-[6.8px] leading-[1.45]">
                @foreach ($card['lines'] as $label => $value)
                    <dt class="text-muted">{{ $label }}</dt>
                    <dd class="m-0 truncate font-semibold">{{ $value }}</dd>
                @endforeach
            </dl>
        </div>
        <div class="flex flex-none flex-col items-center gap-[0.5mm]">
            <div class="size-[16mm] [&>svg]:size-full">{{ \App\Support\QrCode::svg(route('id-cards.verify', ['school' => $school->slug, 'kind' => $kind, 'ulid' => $card['ulid']]), 160) }}</div>
            <span class="text-[5.5px] text-muted">Scan to verify</span>
        </div>
    </div>

    <div class="flex items-center justify-between bg-brand-50 px-[3mm] py-[0.9mm] text-[6px] text-brand-700">
        <span class="font-semibold">{{ $session ? 'Valid for the '.$session->name.' session' : 'Valid while enrolled' }}</span>
        <span class="flex items-center gap-[1mm]">Powered by <img src="{{ asset('images/taaleem-logo.png') }}" alt="{{ config('app.name') }}" class="h-[2.4mm] w-auto"></span>
    </div>
</div>
