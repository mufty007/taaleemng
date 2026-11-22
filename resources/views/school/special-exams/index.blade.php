<x-layouts.app title="Special exams">
    <x-page-header kicker="Results" title="Special exams" description="Mock exams and WAEC, NECO, JAMB, BECE and Common Entrance practice. Marks stay here and never touch termly report cards.">
        <x-slot:actions>
            @if ($session)
                <x-button variant="brand" icon="plus" type="button" x-data x-on:click="$dispatch('open-drawer', 'new-exam')">Set an exam</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($exams->isEmpty())
        <x-card>
            <x-empty-state icon="award" title="No special exams yet" body="Set a mock or a practice exam for your exit classes, enter the marks, then rank the candidates and print result slips.">
                @if ($session)
                    <x-button variant="brand" icon="plus" type="button" x-data x-on:click="$dispatch('open-drawer', 'new-exam')">Set an exam</x-button>
                @endif
            </x-empty-state>
        </x-card>
    @else
        <x-card :padding="false">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] border-collapse text-left text-[15px]">
                    <thead>
                        <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                            <th scope="col" class="px-5 py-3">Exam</th>
                            <th scope="col" class="px-3 py-3">Held</th>
                            <th scope="col" class="px-3 py-3 text-right">Papers</th>
                            <th scope="col" class="px-3 py-3 text-right">Candidates</th>
                            <th scope="col" class="px-5 py-3 text-right">Portal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($exams as $exam)
                            <tr class="border-t border-line hover:bg-canvas">
                                <td class="px-5 py-3">
                                    <a href="{{ route('school.special-exams.show', $exam) }}" class="font-semibold hover:text-brand-600">{{ $exam->title }}</a>
                                    <span class="mt-0.5 flex items-center gap-2 text-xs text-muted">
                                        <x-badge :tone="$exam->kind->tone()">{{ $exam->kind->label() }}</x-badge>
                                        {{ $exam->academicSession->name }} · pass mark {{ $exam->pass_mark }}%
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-sm whitespace-nowrap">{{ $exam->held_on->format('D j M Y') }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ $exam->papers_count }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ $exam->candidates_count }}</td>
                                <td class="px-5 py-3 text-right">
                                    @if ($exam->isPublished())
                                        <x-badge tone="naija">Published</x-badge>
                                    @else
                                        <x-badge>Not yet</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    @if ($session)
        <x-drawer name="new-exam" title="Set an exam" :description="'For the '.$session->name.' session. Every student in the classes you tick becomes a candidate.'" :show="$errors->exam->isNotEmpty()" width="sm:max-w-xl">
            <form method="POST" action="{{ route('school.special-exams.store') }}" class="flex flex-col gap-5" id="new-exam-form">
                @csrf
                <x-field label="Title" name="title" bag="exam" placeholder="e.g. SS 3 WAEC mock" required maxlength="120" />
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-select-field label="Kind of exam" name="kind" :options="$kinds" value="mock" bag="exam" required />
                    <x-field label="Held on" name="held_on" type="date" bag="exam" :value="old('held_on', today()->toDateString())" required />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <x-field label="Pass mark (%)" name="pass_mark" type="number" bag="exam" :value="old('pass_mark', 50)" min="1" max="100" required />
                    <x-field label="Each paper is out of" name="max_score" type="number" bag="exam" :value="old('max_score', 100)" min="10" max="400" required />
                </div>

                <fieldset class="m-0 flex flex-col gap-2 border-0 p-0">
                    <legend class="mb-2 text-sm font-semibold">Who sits it</legend>
                    @if ($arms->isEmpty())
                        <p class="m-0 text-sm text-muted">No classes have students this session yet.</p>
                    @else
                        <div class="grid grid-cols-2 gap-x-4 gap-y-2 sm:grid-cols-3">
                            @foreach ($arms as $arm)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="class_arm_ids[]" value="{{ $arm->id }}" class="size-4 rounded border-line text-brand-600" @checked(in_array($arm->id, old('class_arm_ids', [])))>
                                    {{ $arm->displayName() }}
                                </label>
                            @endforeach
                        </div>
                    @endif
                    @error('class_arm_ids', 'exam')<p class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
                </fieldset>

                <fieldset class="m-0 flex flex-col gap-2 border-0 p-0">
                    <legend class="mb-2 text-sm font-semibold">Papers</legend>
                    <div class="grid max-h-64 grid-cols-1 gap-x-4 gap-y-2 overflow-y-auto rounded-xl border border-line p-3 sm:grid-cols-2">
                        @foreach ($subjects as $id => $name)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="subject_ids[]" value="{{ $id }}" class="size-4 rounded border-line text-brand-600" @checked(in_array($id, old('subject_ids', [])))>
                                {{ $name }}
                            </label>
                        @endforeach
                    </div>
                    <p class="m-0 text-[13px] text-muted">For JAMB practice, tick English and the candidate's three other subjects; each is out of 100 for a score out of 400.</p>
                    @error('subject_ids', 'exam')<p class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
                </fieldset>
            </form>
            <x-slot:footer><x-button variant="brand" icon="check" form="new-exam-form">Set exam</x-button></x-slot:footer>
        </x-drawer>
    @endif
</x-layouts.app>
