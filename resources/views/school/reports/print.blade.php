<x-layouts.print :title="$report['title'].' · '.$periodLabel" :document="$report['title']" :school="$currentSchool" width="max-w-[1000px]">
    <x-slot:meta>
        <div class="mt-1.5 text-lg font-bold">{{ $periodLabel }}</div>
        <div class="text-sm text-muted">Prepared {{ now()->format('j M Y') }}</div>
    </x-slot:meta>

    {{-- Report tables are built for the screen; on A4 they need tighter cells to fit. --}}
    <style>
        @media print {
            .report-print table { min-width: 0 !important; }
            .report-print th, .report-print td { padding: 4px 6px !important; font-size: 10.5px; line-height: 1.35; }
            .report-print th:first-child, .report-print td:first-child { padding-left: 12px !important; }
            .report-print th:last-child, .report-print td:last-child { padding-right: 12px !important; }
        }
    </style>

    <div class="report-print mt-5 flex flex-col gap-4">
        @include('school.reports.partials.'.$report['key'], $figures)
    </div>

    <div class="mt-10 grid grid-cols-2 gap-10 text-sm text-muted break-inside-avoid">
        <span class="border-t border-dashed border-line pt-1 text-center">Prepared by</span>
        <span class="border-t border-dashed border-line pt-1 text-center">Proprietor / Principal</span>
    </div>
</x-layouts.print>
