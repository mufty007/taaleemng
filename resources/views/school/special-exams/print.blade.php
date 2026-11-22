<x-layouts.print :title="$exam->title.' broadsheet'" :document="$exam->kind->label()" :school="$currentSchool" page="A4 landscape" width="max-w-[1100px]">
    <x-slot:meta>
        <div class="mt-1.5 text-lg font-bold">{{ $exam->title }}</div>
        <div class="text-sm text-muted">{{ $exam->held_on->format('j F Y') }} · pass mark {{ $exam->pass_mark }}%</div>
    </x-slot:meta>

    <style>
        @media print {
            .broadsheet th, .broadsheet td { padding: 3px 5px !important; font-size: 10px; line-height: 1.3; }
            .broadsheet table { min-width: 0 !important; }
        }
    </style>

    <div class="broadsheet mt-5 flex flex-col gap-4">
        <section class="grid grid-cols-4 gap-3">
            <x-report.stat label="Sat the exam" :value="$summary['sat'].' of '.$summary['candidates']" />
            <x-report.stat label="Average" :value="$summary['average'] !== null ? $summary['average'].'%' : '—'" tone="sky" />
            <x-report.stat label="Passed" :value="$summary['pass_rate'] !== null ? $summary['pass_rate'].'%' : '—'" :hint="$summary['passed'].' candidates'" tone="naija" />
            <x-report.stat label="Best total" :value="$summary['highest'] !== null ? $summary['highest'].' / '.$results->maxTotal : '—'" tone="gold" />
        </section>

        <x-report.panel title="Ranking" :padding="false" :keep="false">
            @include('school.special-exams._ranking')
        </x-report.panel>

        @include('school.special-exams._papers')
    </div>

    <div class="mt-10 grid grid-cols-2 gap-10 text-sm text-muted break-inside-avoid">
        <span class="border-t border-dashed border-line pt-1 text-center">Exams officer</span>
        <span class="border-t border-dashed border-line pt-1 text-center">Principal</span>
    </div>
</x-layouts.print>
