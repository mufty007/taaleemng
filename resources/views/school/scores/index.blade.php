<x-layouts.app title="Score entry">
    <x-page-header kicker="Results" title="Score entry" :description="$term ? 'Entering scores for '.$term->name.' '.$term->academicSession->name.'.'.($seesAll ? ' You can enter scores for every subject.' : ' These are the subjects you teach.') : 'Set up the current term first.'" />

    <div x-data="{ search: '' }" class="flex flex-col gap-4">
        <div class="relative max-w-md">
            <label for="score-search" class="sr-only">Find a class or subject</label>
            <x-lucide-search class="pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2 text-muted" aria-hidden="true" />
            <input id="score-search" type="search" x-model="search" placeholder="Find a class or subject, e.g. JSS 2A Maths" class="field bg-white pl-10">
        </div>

        <x-card :padding="false">
            @if ($classSubjects->isEmpty())
                <x-empty-state icon="file-pen-line" title="No subjects to score" body="Ask your admin to assign you subjects on the Classes or Subjects page." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] border-collapse text-left text-[15px]">
                        <thead>
                            <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                                <th scope="col" class="px-5 py-3 font-semibold">Class</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Subject</th>
                                @if ($seesAll)<th scope="col" class="px-3 py-3 font-semibold">Teacher</th>@endif
                                <th scope="col" class="px-3 py-3 font-semibold">Progress</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold"><span class="sr-only">Open</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($classSubjects as $classSubject)
                                @php($percent = $progress[$classSubject->id])
                                <tr x-show="search === '' || @js(\Illuminate\Support\Str::lower($classSubject->classArm->displayName().' '.$classSubject->classArm->level->name.$classSubject->classArm->name.' '.$classSubject->subject->name.' '.$classSubject->subject->code)).includes(search.toLowerCase().replace('maths', 'math'))" class="border-t border-line hover:bg-canvas">
                                    <td class="px-5 py-3 font-semibold">{{ $classSubject->classArm->displayName() }}</td>
                                    <td class="px-3 py-3">{{ $classSubject->subject->name }}</td>
                                    @if ($seesAll)<td class="px-3 py-3 text-sm text-muted">{{ $classSubject->teacher?->fullName() ?? '—' }}</td>@endif
                                    <td class="px-3 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="h-2 w-28 overflow-hidden rounded-full bg-chip" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Scores entered">
                                                <div @class(['h-full rounded-full', 'bg-naija' => $percent === 100, 'bg-brand' => $percent < 100]) style="width: {{ $percent }}%"></div>
                                            </div>
                                            <span class="text-sm text-muted">{{ $percent }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <x-button variant="soft" size="sm" :href="route('school.scores.edit', $classSubject)" icon="file-pen-line">Enter scores</x-button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
</x-layouts.app>
