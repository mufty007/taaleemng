<x-layouts.app :title="$assignment->title">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.classwork.index', ['class_subject' => $assignment->class_subject_id]) }}#assignments" class="hover:text-ink">Classwork</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">{{ $assignment->title }}</span>
    </div>

    @php
        $marked = $submissions->whereNotNull('marked_at');
        $firstToMark = $students->map(fn ($student) => $submissions->get($student->id))->filter()->first(fn ($submission) => $submission->marked_at === null);
    @endphp

    <x-page-header :kicker="$assignment->classSubject->classArm->displayName().' · '.$assignment->classSubject->subject->name" :title="$assignment->title" :description="'Due '.$assignment->due_at->format('l j F, g:ia').' · '.$submissions->count().' of '.$students->count().' handed in · '.$marked->count().' marked'.($assignment->max_score ? ' · out of '.$assignment->max_score : '')">
        <x-slot:actions>
            @if ($firstToMark)
                <x-button variant="brand" icon="pen-line" :href="route('school.classwork.submissions.show', $firstToMark)">Start marking</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-stat-card label="Handed in" :value="$submissions->count().' / '.$students->count()" icon="inbox" />
        <x-stat-card label="To mark" :value="(string) ($submissions->count() - $marked->count())" icon="pen-line" tone="gold" />
        <x-stat-card label="Late" :value="(string) $submissions->filter(fn ($submission) => $submission->isLate($assignment))->count()" icon="alarm-clock" tone="sky" />
        <x-stat-card label="Average mark" :value="$marked->whereNotNull('score')->count() ? rtrim(rtrim(number_format($marked->whereNotNull('score')->avg('score'), 1), '0'), '.').($assignment->max_score ? ' / '.$assignment->max_score : '') : '—'" icon="bar-chart-3" tone="naija" />
    </div>

    <x-tabs :tabs="['work' => ['label' => 'Students', 'count' => $students->count()], 'brief' => 'Instructions']" label="Assignment">
        <x-tab name="work">
            <x-card :padding="false">
                @if ($students->isEmpty())
                    <x-empty-state icon="users" title="No students in this class" />
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] border-collapse text-left text-[15px]">
                            <thead>
                                <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                                    <th scope="col" class="px-5 py-3">Student</th>
                                    <th scope="col" class="px-3 py-3">Handed in</th>
                                    <th scope="col" class="px-3 py-3">Work</th>
                                    <th scope="col" class="px-3 py-3">Mark</th>
                                    <th scope="col" class="px-5 py-3 text-right"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($students->sortBy(fn ($student) => $submissions->has($student->id) ? 0 : 1) as $student)
                                    @php($submission = $submissions->get($student->id))
                                    <tr class="border-t border-line">
                                        <td class="px-5 py-3">
                                            <span class="flex items-center gap-2.5 font-medium"><x-avatar :person="$student" class="size-8 rounded-full text-[11px]" />{{ $student->registerName() }}</span>
                                        </td>
                                        <td class="px-3 py-3 text-sm">
                                            @if ($submission)
                                                {{ $submission->submitted_at->format('D j M, g:ia') }}
                                                @if ($submission->isLate($assignment))<x-badge tone="gold" class="ml-1">Late</x-badge>@endif
                                            @elseif ($assignment->isPastDue())
                                                <x-badge tone="danger">Missing</x-badge>
                                            @else
                                                <span class="text-muted">Not yet</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-sm text-muted">
                                            @if ($submission)
                                                {{ collect([count($submission->files ?? []) ? count($submission->files).' '.\Illuminate\Support\Str::plural('file', count($submission->files)) : null, $submission->body ? 'typed answer' : null])->filter()->join(' + ') }}
                                            @endif
                                        </td>
                                        <td class="px-3 py-3">
                                            @if ($submission?->marked_at)
                                                <x-badge tone="naija">{{ $submission->score !== null ? rtrim(rtrim(number_format($submission->score, 1), '0'), '.').($assignment->max_score ? ' / '.$assignment->max_score : '') : 'Marked' }}</x-badge>
                                            @elseif ($submission)
                                                <x-badge tone="gold">To mark</x-badge>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            @if ($submission)
                                                <x-button :variant="$submission->marked_at ? 'ghost' : 'secondary'" size="sm" :icon="$submission->marked_at ? 'eye' : 'pen-line'" :href="route('school.classwork.submissions.show', $submission)">{{ $submission->marked_at ? 'View' : 'Mark' }}</x-button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </x-tab>
        <x-tab name="brief">
            <x-card class="max-w-3xl">
                <div class="text-[15px] whitespace-pre-line">{{ $assignment->instructions }}</div>
                @if ($assignment->files)
                    <h3 class="m-0 mt-5 mb-2 text-sm font-semibold text-muted">Attached for students</h3>
                    <x-file-list :files="$assignment->files" :url="fn ($index) => route('school.classwork.assignments.file', [$assignment, $index])" />
                @endif
            </x-card>
        </x-tab>
    </x-tabs>
</x-layouts.app>
