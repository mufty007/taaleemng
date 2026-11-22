<x-layouts.app :title="$classArm->displayName()">
    <x-page-header
        :kicker="$classArm->level->name.($classArm->stream ? ' · '.$classArm->stream->name : '')"
        :title="$classArm->displayName()"
        :description="($session ? $session->name.' · ' : '').$enrolments->count().' students · '.$classArm->classSubjects->count().' subjects'"
    >
        <x-slot:actions>
            @if ($myClassSubjectId)
                <x-button variant="secondary" icon="notebook-pen" :href="route('school.classwork.index', ['class_subject' => $myClassSubjectId, 'new' => 'assignment'])">New assignment</x-button>
                <x-button icon="monitor-check" :href="route('school.classwork.index', ['class_subject' => $myClassSubjectId, 'new' => 'quiz'])">New quiz</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @php
        $counts = $register->counts();
        $marked = array_sum($counts);
    @endphp
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat-card label="Students" :value="(string) $enrolments->count()" icon="users" :hint="$enrolments->where('student.gender', \App\Enums\Gender::Female)->count().' girls · '.$enrolments->where('student.gender', \App\Enums\Gender::Male)->count().' boys'" />
        <x-stat-card label="Today" :value="$marked ? ($counts['present'] + $counts['late']).' in' : 'Not taken'" icon="clipboard-check" :tone="$marked ? 'naija' : 'gold'" :hint="$marked ? $counts['absent'].' absent · '.$counts['late'].' late' : ($register->locked ? 'No register today' : 'Register not taken yet')" />
        <x-stat-card label="Attendance this term" :value="$attendanceRate !== null ? $attendanceRate.'%' : '—'" icon="calendar-check" tone="sky" :hint="$register->term?->name" />
        <x-stat-card label="Classwork this term" :value="(string) ($assignments->count() + $quizzes->count())" icon="notebook-pen" tone="gold" :hint="$assignments->count().' assignments · '.$quizzes->count().' quizzes'" />
    </div>

    <x-tabs
        :tabs="array_filter([
            'register' => $canRegister || $myClassSubjectId ? ['label' => 'Register', 'count' => $marked.'/'.$enrolments->count()] : null,
            'students' => ['label' => 'Students', 'count' => $enrolments->count()],
            'classwork' => $myClassSubjectId ? ['label' => 'Classwork', 'count' => $assignments->count() + $quizzes->count()] : null,
            'timetable' => 'Timetable',
            'subjects' => ['label' => 'Subjects & teachers', 'count' => $classArm->classSubjects->count()],
            'settings' => 'Settings',
        ])"
        :default="$canRegister && ! $register->locked && ! $register->isTaken() ? 'register' : 'students'"
        label="Class sections"
    >
        @if ($canRegister || $myClassSubjectId)
            <x-tab name="register">
                @include('school.attendance._register', ['compact' => true, 'readOnly' => ! $canRegister])
            </x-tab>
        @endif

        <x-tab name="timetable">
            <x-card :padding="false" class="p-2">
                @if ($timetable)
                    @include('school.timetable._week', ['show' => 'class', 'grid' => $timetable])
                @else
                    <x-empty-state icon="calendar-days" title="No timetable for this class yet">
                        @can('timetable.manage')
                            <x-button icon="calendar-days" :href="route('school.timetable.index', ['class' => $classArm->ulid])">Build it</x-button>
                        @endcan
                    </x-empty-state>
                @endif
            </x-card>
        </x-tab>

        @if ($myClassSubjectId)
            <x-tab name="classwork">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-wrap gap-2">
                        <x-button variant="brand" icon="notebook-pen" :href="route('school.classwork.index', ['class_subject' => $myClassSubjectId, 'new' => 'assignment'])">New assignment</x-button>
                        <x-button variant="secondary" icon="monitor-check" :href="route('school.classwork.index', ['class_subject' => $myClassSubjectId, 'new' => 'quiz'])">New quiz or exam</x-button>
                    </div>
                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                        <x-card :padding="false">
                            <h2 class="m-0 border-b border-line px-5 py-3 text-base font-semibold">Assignments</h2>
                            @forelse ($assignments as $assignment)
                                <a href="{{ route('school.classwork.assignments.show', $assignment) }}" class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-b-0 hover:bg-canvas">
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate font-medium">{{ $assignment->title }}</div>
                                        <div class="text-sm text-muted">{{ $assignment->classSubject->subject->name }} · due {{ $assignment->due_at->format('D j M') }}</div>
                                    </div>
                                    <span class="text-sm whitespace-nowrap text-muted">{{ $assignment->submissions_count }}/{{ $enrolments->count() }} in</span>
                                </a>
                            @empty
                                <p class="m-0 px-5 py-4 text-sm text-muted">No assignments this term.</p>
                            @endforelse
                        </x-card>
                        <x-card :padding="false">
                            <h2 class="m-0 border-b border-line px-5 py-3 text-base font-semibold">Quizzes, tests &amp; exams</h2>
                            @forelse ($quizzes as $quiz)
                                <a href="{{ route('school.classwork.quizzes.edit', $quiz) }}" class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-b-0 hover:bg-canvas">
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate font-medium">{{ $quiz->title }}</div>
                                        <div class="text-sm text-muted">{{ $quiz->classSubject->subject->name }} · {{ $quiz->kind->label() }} · {{ $quiz->questions_count }} questions</div>
                                    </div>
                                    <x-badge :tone="$quiz->status === \App\Enums\ClassworkStatus::Published ? ($quiz->isOpen() ? 'naija' : 'neutral') : 'gold'">{{ $quiz->status === \App\Enums\ClassworkStatus::Published ? ($quiz->isOpen() ? 'Open' : ($quiz->hasClosed() ? 'Closed' : 'Scheduled')) : 'Draft' }}</x-badge>
                                    <span class="text-sm whitespace-nowrap text-muted">{{ $quiz->attempts_count }} done</span>
                                </a>
                            @empty
                                <p class="m-0 px-5 py-4 text-sm text-muted">No quizzes this term.</p>
                            @endforelse
                        </x-card>
                    </div>
                </div>
            </x-tab>
        @endif

        <x-tab name="students">
        <x-card :padding="false">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-4">
                <h2 class="m-0 text-lg font-semibold">Students</h2>
                <x-button variant="ghost" size="sm" icon="list" :href="route('school.students.index', ['class' => $classArm->ulid])">Full student list</x-button>
            </div>
            @if ($enrolments->isEmpty())
                <x-empty-state icon="graduation-cap" title="No students in this class yet" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] border-collapse text-left text-[15px]">
                        <thead>
                            <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                                <th scope="col" class="w-12 px-6 py-3 font-semibold">#</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Student</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Admission no.</th>
                                <th scope="col" class="px-6 py-3 font-semibold">Parent’s phone</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($enrolments as $index => $enrolment)
                                @php($student = $enrolment->student)
                                <tr class="border-t border-line">
                                    <td class="px-6 py-3 text-muted">{{ $index + 1 }}</td>
                                    <td class="px-3 py-3">
                                        <a href="{{ route('school.students.show', $student) }}" class="flex items-center gap-2.5 font-medium hover:text-brand-600">
                                            <x-avatar :person="$student" class="size-8 rounded-full text-[11px]" />
                                            {{ $student->registerName() }}
                                        </a>
                                    </td>
                                    <td class="px-3 py-3 font-mono text-sm">{{ $student->admission_no }}</td>
                                    <td class="px-6 py-3 text-sm">
                                        @if ($guardian = $student->guardians->first())
                                            <a href="tel:{{ $guardian->phone }}" class="text-brand-600 hover:text-ink">{{ $guardian->phone }}</a>
                                        @else
                                            <span class="text-muted">—</span>
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

        <x-tab name="settings" class="max-w-xl">
            <x-card>
                <h2 class="m-0 text-lg font-semibold">Form teacher</h2>
                @if ($canManage)
                    <form method="POST" action="{{ route('school.classes.update', $classArm) }}" class="mt-4 flex flex-wrap items-end gap-3">
                        @csrf
                        @method('PATCH')
                        <div class="min-w-[220px] flex-1">
                            <x-select-field label="Form teacher" name="form_teacher_id" :options="$teacherOptions" :value="$classArm->form_teacher_id" placeholder="No form teacher" />
                        </div>
                        <x-button variant="secondary">Save</x-button>
                    </form>
                @else
                    <p class="m-0 mt-2 text-[15px]">{{ $classArm->formTeacher?->fullName() ?? 'Not assigned yet' }}</p>
                @endif
            </x-card>
        </x-tab>

        <x-tab name="subjects">
            <x-card :padding="false">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <h2 class="m-0 text-lg font-semibold">Subjects &amp; teachers</h2>
                    @if ($canManage && $subjectOptions)
                        <x-button variant="soft" size="sm" icon="plus" type="button" x-data x-on:click="$dispatch('open-drawer', 'assign-subject')">Add subject</x-button>
                    @endif
                </div>
                <ul class="m-0 list-none p-0">
                    @foreach ($classArm->classSubjects->sortBy('subject.name') as $classSubject)
                        <li class="flex flex-wrap items-center gap-3 border-b border-line px-6 py-3 last:border-b-0">
                            <div class="min-w-0 flex-1">
                                <div class="font-medium">{{ $classSubject->subject->name }}</div>
                                @unless ($canManage)
                                    <div class="text-sm text-muted">{{ $classSubject->teacher?->fullName() ?? 'No teacher yet' }}</div>
                                @endunless
                            </div>
                            @if ($canManage)
                                <form method="POST" action="{{ route('school.class-subjects.update', $classSubject) }}" class="flex items-center gap-2" x-data>
                                    @csrf
                                    @method('PATCH')
                                    <label for="teacher-{{ $classSubject->id }}" class="sr-only">Teacher for {{ $classSubject->subject->name }}</label>
                                    <select id="teacher-{{ $classSubject->id }}" name="teacher_id" x-on:change="$el.form.requestSubmit()" class="field w-56 py-2 text-sm">
                                        <option value="">No teacher yet</option>
                                        @foreach ($teacherOptions as $id => $label)
                                            <option value="{{ $id }}" @selected($classSubject->teacher_id === $id)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <noscript><x-button variant="secondary" size="sm">Save</x-button></noscript>
                                </form>
                                <form method="POST" action="{{ route('school.class-subjects.destroy', $classSubject) }}" data-confirm="Remove {{ $classSubject->subject->name }} from {{ $classArm->displayName() }}?" data-confirm-title="Remove subject?" data-confirm-action="Remove">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="grid size-9 place-items-center rounded-lg text-muted hover:bg-danger-50 hover:text-danger" aria-label="Remove {{ $classSubject->subject->name }}">
                                        <x-lucide-trash-2 class="size-4" aria-hidden="true" />
                                    </button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-card>
        </x-tab>
    </x-tabs>

    @if ($canManage)
        <x-drawer name="assign-subject" title="Add a subject to {{ $classArm->displayName() }}" :show="$errors->assignSubject->isNotEmpty()">
            <form method="POST" action="{{ route('school.class-subjects.store', $classArm) }}" class="flex flex-col gap-4">
                @csrf
                <x-select-field label="Subject" name="subject_id" :options="$subjectOptions" bag="assignSubject" required />
                <x-select-field label="Teacher" name="teacher_id" :options="$teacherOptions" placeholder="Choose later" bag="assignSubject" />
                <x-button variant="brand" class="self-start">Add subject</x-button>
            </form>
        </x-drawer>
    @endif
</x-layouts.app>
