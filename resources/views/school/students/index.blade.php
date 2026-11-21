<x-layouts.app title="Students">
    <x-page-header
        kicker="People"
        title="Students"
        :description="$canSeeAll ? number_format($students->total()).' students match.' : 'Students in the classes you teach.'"
    >
        @can('create', \App\Models\Student::class)
            <x-slot:actions>
                @can('students.manage')
                    <x-button variant="secondary" :href="route('school.students.import.create')" icon="file-spreadsheet">Import from Excel</x-button>
                @endcan
                <x-button :href="route('school.students.create')" icon="user-plus">Enrol a student</x-button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <x-card :padding="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-line px-5 py-4" role="search">
            <div class="min-w-[220px] flex-[2]">
                <label for="search" class="sr-only">Search students</label>
                <div class="relative">
                    <x-lucide-search class="pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2 text-muted" aria-hidden="true" />
                    <input id="search" name="search" type="search" value="{{ $search }}" placeholder="Name or admission number" class="field pl-10">
                </div>
            </div>
            <div class="min-w-[160px] flex-1">
                <label for="class" class="sr-only">Class</label>
                <select id="class" name="class" class="field" x-data x-on:change="$el.form.requestSubmit()">
                    <option value="">All classes</option>
                    @foreach ($classOptions as $ulid => $label)
                        <option value="{{ $ulid }}" @selected($selectedArm?->ulid === $ulid)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[140px]">
                <label for="status" class="sr-only">Status</label>
                <select id="status" name="status" class="field" x-data x-on:change="$el.form.requestSubmit()">
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <x-button variant="secondary">Search</x-button>
        </form>

        @if ($students->isEmpty())
            <x-empty-state icon="graduation-cap" title="No students found" :body="$search ? 'Try a different name or admission number.' : 'Students you enrol will appear here.'" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] border-collapse text-left text-[15px]">
                    <thead>
                        <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                            <th scope="col" class="px-5 py-3 font-semibold">Student</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Admission no.</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Class</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Age</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Parent</th>
                            <th scope="col" class="px-5 py-3 font-semibold"><span class="sr-only">Open</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $student)
                            @php($guardian = $student->guardians->first())
                            <tr class="border-t border-line hover:bg-canvas">
                                <td class="px-5 py-3">
                                    <a href="{{ route('school.students.show', $student) }}" class="flex items-center gap-3">
                                        <x-avatar :person="$student" class="size-9 rounded-full text-xs" />
                                        <span class="min-w-0">
                                            <span class="block truncate font-semibold">{{ $student->registerName() }}</span>
                                            <span class="block text-sm text-muted">{{ $student->gender->label() }}</span>
                                        </span>
                                    </a>
                                </td>
                                <td class="px-3 py-3 font-mono text-sm">{{ $student->admission_no }}</td>
                                <td class="px-3 py-3">
                                    @if ($student->currentEnrolment)
                                        <x-badge tone="brand">{{ $student->currentEnrolment->classArm->displayName() }}</x-badge>
                                    @else
                                        <span class="text-muted">Not enrolled</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-muted">{{ $student->age() ?? '—' }}</td>
                                <td class="px-3 py-3 text-sm">
                                    @if ($guardian)
                                        <span class="block">{{ $guardian->fullName() }}</span>
                                        <a href="tel:{{ $guardian->phone }}" class="text-brand-600 hover:text-ink">{{ $guardian->phone }}</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('school.students.show', $student) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-ink" aria-label="Open {{ $student->fullName() }}">Open <x-lucide-arrow-right class="size-4" aria-hidden="true" /></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($students->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $students->links() }}</div>
            @endif
        @endif
    </x-card>
</x-layouts.app>
