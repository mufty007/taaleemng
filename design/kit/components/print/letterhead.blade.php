{{--
    The top of every printed document: the school's logo and details on the left,
    what the document is on the right, over a Ta'aleem-blue rule.
--}}
@props(['school', 'document'])

<header {{ $attributes->class(['flex items-start justify-between gap-6 border-b-2 border-brand-600 pb-4']) }}>
    <div class="flex min-w-0 items-center gap-4">
        <x-school-crest :school="$school" class="size-16 flex-none text-xl" />
        <div class="min-w-0">
            <h1 class="m-0 text-xl leading-tight font-bold tracking-[-0.01em] text-ink uppercase sm:text-2xl">{{ $school->name }}</h1>
            @if ($school->motto)<p class="m-0 text-sm text-muted italic">“{{ $school->motto }}”</p>@endif
            <p class="m-0 mt-0.5 text-[13px] text-muted">{{ collect([$school->address, $school->lga, $school->state])->filter()->join(', ') }}</p>
            <p class="m-0 text-[13px] text-muted">{{ collect([$school->phone, $school->email, $school->website])->filter()->join(' · ') }}</p>
        </div>
    </div>
    <div class="flex-none text-right">
        <div class="inline-block rounded-md bg-brand-600 px-2.5 py-1 text-[11px] font-bold tracking-[0.14em] text-white uppercase">{{ $document }}</div>
        {{ $slot }}
    </div>
</header>
