{{--
    ID cards on A4: eight cards to a page, each page of fronts followed by its backs
    in mirrored order, so printing double-sided (flip on the long edge) lines them up.
--}}
@php
    $school = $currentSchool;
    $pages = $cards->chunk(8);
@endphp

<x-layouts.base :title="$title">
    <x-slot:head>
        <style>
            @media print {
                @page { size: A4 portrait; margin: 10mm; }
                .card-page { break-after: page; margin: 0 !important; padding: 0 !important; box-shadow: none !important; border: 0 !important; }
                .card-page:last-child { break-after: auto; }
            }
        </style>
    </x-slot:head>

    <x-print.toolbar width="max-w-[210mm]">
        <span class="text-sm text-muted">{{ $cards->count() }} {{ \Illuminate\Support\Str::plural('card', $cards->count()) }} · {{ $pages->count() }} {{ \Illuminate\Support\Str::plural('sheet', $pages->count()) }}{{ $backs ? ', printed double-sided' : '' }}</span>
    </x-print.toolbar>

    @if ($cards->isEmpty())
        <div class="mx-auto my-10 max-w-md px-4"><x-card><x-empty-state icon="id-card" title="No cards to print" body="Choose a class with students, or tick the people you want cards for." /></x-card></div>
    @endif

    <div class="mx-auto flex max-w-[210mm] flex-col items-center gap-6 py-6 print:block print:py-0">
        @foreach ($pages as $page)
            <section class="card-page grid w-[210mm] grid-cols-2 content-start justify-center gap-x-[6mm] gap-y-[5mm] bg-white p-[10mm] shadow-card" aria-label="Sheet {{ $loop->iteration }} fronts">
                @foreach ($page as $card)
                    @include('school.id-cards._front')
                @endforeach
            </section>
            @if ($backs)
                <section class="card-page grid w-[210mm] grid-cols-2 content-start justify-center gap-x-[6mm] gap-y-[5mm] bg-white p-[10mm] shadow-card" aria-label="Sheet {{ $loop->iteration }} backs">
                    @foreach ($page->values()->chunk(2) as $row)
                        {{-- Mirror each row so the backs land behind their fronts. --}}
                        @if ($row->count() === 1)<div aria-hidden="true"></div>@endif
                        @foreach ($row->reverse() as $card)
                            @include('school.id-cards._back')
                        @endforeach
                    @endforeach
                </section>
            @endif
        @endforeach
    </div>
</x-layouts.base>
