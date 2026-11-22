<x-layouts.app :title="$exam->title">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.special-exams.index') }}" class="hover:text-ink">Special exams</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">{{ $exam->title }}</span>
    </div>

    <x-page-header :kicker="$exam->kind->label()" :title="$exam->title" :description="$exam->held_on->format('l j F Y').' · '.$results->papers->count().' '.Str::plural('paper', $results->papers->count()).' out of '.$results->maxTotal.' in all · pass mark '.$exam->pass_mark.'%'">
        <x-slot:actions>
            <x-button variant="brand" icon="pencil-line" :href="route('school.special-exams.scores', $exam)">Enter marks</x-button>
            <form method="POST" action="{{ route('school.special-exams.publish', $exam) }}">
                @csrf
                <x-button variant="secondary" :icon="$exam->isPublished() ? 'eye-off' : 'eye'">{{ $exam->isPublished() ? 'Hide from portal' : 'Publish to portal' }}</x-button>
            </form>
            <x-button data-native variant="secondary" icon="printer" :href="route('school.special-exams.print', $exam)">Broadsheet</x-button>
            <x-button data-native variant="secondary" icon="scroll-text" :href="route('school.special-exams.slips', $exam)">Result slips</x-button>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-report.stat label="Sat the exam" :value="$summary['sat'].' of '.$summary['candidates']" :hint="$summary['candidates'] - $summary['sat'] > 0 ? ($summary['candidates'] - $summary['sat']).' with no marks yet' : 'Every candidate has marks'" />
        <x-report.stat label="Average" :value="$summary['average'] !== null ? $summary['average'].'%' : '—'" hint="Of the whole exam" tone="sky" />
        <x-report.stat label="Passed" :value="$summary['pass_rate'] !== null ? $summary['pass_rate'].'%' : '—'" :hint="$summary['passed'].' at or above '.$exam->pass_mark.'%'" tone="naija" />
        <x-report.stat label="Best total" :value="$summary['highest'] !== null ? $summary['highest'].' / '.$results->maxTotal : '—'" tone="gold" />
    </section>

    @include('school.special-exams._papers')

    <x-report.panel title="Ranking" description="Highest total first. Candidates with no marks yet are listed last." :padding="false" :keep="false">
        @include('school.special-exams._ranking')
    </x-report.panel>

    <div class="flex justify-end">
        <form method="POST" action="{{ route('school.special-exams.destroy', $exam) }}" data-confirm="This deletes {{ $exam->title }} and every mark entered for it. It can’t be undone." data-confirm-title="Delete this exam?" data-confirm-action="Delete exam">
            @csrf
            @method('DELETE')
            <x-button variant="ghost" icon="trash-2" class="text-danger hover:bg-danger-50 hover:text-danger">Delete exam</x-button>
        </form>
    </div>
</x-layouts.app>
