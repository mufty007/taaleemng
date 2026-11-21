<x-layouts.app title="ID cards">
    <x-page-header kicker="People" title="ID cards" description="Print student and staff ID cards with the school logo, a photo and a QR code anyone can scan to check the card is genuine. Eight cards to an A4 sheet; use card stock or print on sticker paper and laminate." />

    @php
        $tabs = array_filter([
            'students' => $canStudents ? ['label' => 'Students', 'count' => $students->count()] : null,
            'staff' => $canStaff ? ['label' => 'Staff', 'count' => $staff->count()] : null,
        ]);
        $sample = $students->first() ?? $staff->first();
    @endphp

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_auto]">
        <x-tabs :tabs="$tabs" label="Card type">
            @if ($canStudents)
                <x-tab name="students">
                    <x-card :padding="false" x-data="{ all: false }">
                        <div class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4">
                            <form method="GET" class="flex items-center gap-2">
                                <label for="class" class="text-sm font-semibold">Class</label>
                                <select id="class" name="class" class="field w-48 bg-white py-2" x-data x-on:change="$el.form.requestSubmit()">
                                    @foreach ($arms as $option)
                                        <option value="{{ $option->ulid }}" @selected($arm?->is($option))>{{ $option->displayName() }} ({{ $option->enrolments_count }})</option>
                                    @endforeach
                                </select>
                            </form>
                            <span class="text-sm text-muted">{{ $students->whereNotNull('photo_path')->count() }} of {{ $students->count() }} have photos</span>
                            @if ($arm)
                                <form method="GET" action="{{ route('school.id-cards.students') }}" target="_blank" id="print-students" class="ml-auto flex flex-wrap items-center gap-3">
                                    <input type="hidden" name="class" value="{{ $arm->ulid }}">
                                    <input type="hidden" name="backs" value="0">
                                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="backs" value="1" checked class="size-4 rounded border-line"> Print the backs too</label>
                                    <x-button variant="brand" icon="printer">Print cards</x-button>
                                </form>
                            @endif
                        </div>
                        @if ($students->isEmpty())
                            <x-empty-state icon="graduation-cap" title="No students to print" body="Enrol students in a class first." />
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[560px] border-collapse text-left text-[15px]">
                                    <thead>
                                        <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                                            <th scope="col" class="w-12 px-5 py-3"><input type="checkbox" class="size-4 rounded border-line" aria-label="Select everyone" x-model="all" x-on:change="$root.querySelectorAll('[data-pick]').forEach((box) => box.checked = all)"></th>
                                            <th scope="col" class="px-3 py-3">Student</th>
                                            <th scope="col" class="px-3 py-3">Admission no.</th>
                                            <th scope="col" class="px-5 py-3 text-right">Photo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($students as $student)
                                            <tr class="border-t border-line">
                                                <td class="px-5 py-2.5"><input type="checkbox" name="only[]" value="{{ $student->ulid }}" form="print-students" data-pick class="size-4 rounded border-line" aria-label="Print a card for {{ $student->fullName() }}"></td>
                                                <td class="px-3 py-2.5">
                                                    <a href="{{ route('school.students.show', $student) }}" class="flex items-center gap-2.5 font-medium hover:text-brand-600">
                                                        <x-avatar :person="$student" class="size-9 rounded-lg text-xs" />
                                                        {{ $student->registerName() }}
                                                    </a>
                                                </td>
                                                <td class="px-3 py-2.5 font-mono text-sm">{{ $student->admission_no }}</td>
                                                <td class="px-5 py-2.5 text-right">
                                                    <form method="POST" action="{{ route('school.students.photo', $student) }}" enctype="multipart/form-data" class="inline-flex" x-data>
                                                        @csrf
                                                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-semibold text-brand-600 hover:bg-brand-50">
                                                            <x-lucide-camera class="size-4" aria-hidden="true" /> {{ $student->photo_path ? 'Change' : 'Add photo' }}
                                                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only" x-on:change="$el.files.length && $el.form.requestSubmit()">
                                                        </label>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="m-0 border-t border-line px-5 py-3 text-sm text-muted">Tick students to print only their cards, or leave all unticked to print the whole class.</p>
                        @endif
                    </x-card>
                </x-tab>
            @endif

            @if ($canStaff)
                <x-tab name="staff">
                    <x-card :padding="false" x-data="{ all: false }">
                        <div class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4">
                            <span class="text-sm text-muted">{{ $staff->whereNotNull('photo_path')->count() }} of {{ $staff->count() }} active staff have photos</span>
                            <form method="GET" action="{{ route('school.id-cards.staff') }}" target="_blank" id="print-staff" class="ml-auto flex flex-wrap items-center gap-3">
                                <input type="hidden" name="backs" value="0">
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="backs" value="1" checked class="size-4 rounded border-line"> Print the backs too</label>
                                <x-button variant="brand" icon="printer">Print staff cards</x-button>
                            </form>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[560px] border-collapse text-left text-[15px]">
                                <thead>
                                    <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                                        <th scope="col" class="w-12 px-5 py-3"><input type="checkbox" class="size-4 rounded border-line" aria-label="Select everyone" x-model="all" x-on:change="$root.querySelectorAll('[data-pick]').forEach((box) => box.checked = all)"></th>
                                        <th scope="col" class="px-3 py-3">Name</th>
                                        <th scope="col" class="px-3 py-3">Role</th>
                                        <th scope="col" class="px-5 py-3 text-right">Photo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($staff as $member)
                                        <tr class="border-t border-line">
                                            <td class="px-5 py-2.5"><input type="checkbox" name="only[]" value="{{ $member->ulid }}" form="print-staff" data-pick class="size-4 rounded border-line" aria-label="Print a card for {{ $member->fullName() }}"></td>
                                            <td class="px-3 py-2.5">
                                                <a href="{{ route('school.staff.show', $member) }}" class="flex items-center gap-2.5 font-medium hover:text-brand-600">
                                                    <x-avatar :person="$member" class="size-9 rounded-lg text-xs" />
                                                    {{ $member->fullName() }}
                                                </a>
                                            </td>
                                            <td class="px-3 py-2.5 text-sm">{{ $member->designation ?? '—' }} <span class="font-mono text-muted">· {{ $member->staff_no }}</span></td>
                                            <td class="px-5 py-2.5 text-right">
                                                <form method="POST" action="{{ route('school.staff.photo', $member) }}" enctype="multipart/form-data" class="inline-flex" x-data>
                                                    @csrf
                                                    <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-semibold text-brand-600 hover:bg-brand-50">
                                                        <x-lucide-camera class="size-4" aria-hidden="true" /> {{ $member->photo_path ? 'Change' : 'Add photo' }}
                                                        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only" x-on:change="$el.files.length && $el.form.requestSubmit()">
                                                    </label>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                </x-tab>
            @endif
        </x-tabs>

        @if ($sample)
            <aside class="flex flex-col gap-3 xl:sticky xl:top-24">
                <h2 class="m-0 text-sm font-semibold text-muted">How the cards look</h2>
                @php
                    $kind = $sample instanceof \App\Models\Student ? 'student' : 'staff';
                    $school = $currentSchool;
                    $card = [
                        'ulid' => $sample->ulid,
                        'person' => $sample,
                        'name' => $sample->fullName(),
                        'number' => $sample instanceof \App\Models\Student ? $sample->admission_no : $sample->staff_no,
                        'role' => $sample instanceof \App\Models\Student ? 'Student' : ($sample->designation ?: 'Staff'),
                        'lines' => $sample instanceof \App\Models\Student ? array_filter(['Class' => $arm?->displayName(), 'Gender' => $sample->gender->label(), 'Blood group' => $sample->blood_group]) : array_filter(['Department' => $sample->department]),
                        'emergency' => $sample instanceof \App\Models\Student && $sample->guardians->first() ? $sample->guardians->first()->fullName().' · '.$sample->guardians->first()->phone : null,
                    ];
                @endphp
                <div class="origin-top-left">@include('school.id-cards._front')</div>
                <div>@include('school.id-cards._back')</div>
                @unless ($currentSchool->logo_path)
                    <p class="m-0 max-w-[85.6mm] text-sm text-muted">Add your school logo on <a href="{{ route('school.profile.edit') }}" class="font-semibold text-brand-600">School information</a> and it appears on every card.</p>
                @endunless
            </aside>
        @endif
    </div>
</x-layouts.app>
