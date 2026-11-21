@php
    use App\Enums\ApplicantStatus;
@endphp

<x-layouts.app :title="$applicant->fullName().' · Admissions'">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.admissions.index', ['stage' => $applicant->status->stage()]) }}" class="hover:text-ink">Admissions</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">{{ $applicant->application_no }}</span>
    </div>

    <x-page-header :kicker="'Application '.$applicant->application_no" :title="$applicant->fullName()" :description="'Applying for '.$applicant->classLevel->name.' in '.$applicant->academicSession->name.' · '.($applicant->source === 'online' ? 'applied online' : 'recorded at the office').' on '.$applicant->created_at->format('j M Y')">
        <x-slot:actions>
            <x-badge :tone="$applicant->status->tone()" class="px-3 py-1.5 text-sm">{{ $applicant->status->label() }}</x-badge>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,24rem)]">
        <x-tabs :tabs="['child' => 'Child', 'parent' => 'Parent', 'notes' => 'Exam & notes']" label="Application">
            <x-tab name="child">
                <x-card>
                    <dl class="m-0 grid grid-cols-1 gap-x-8 gap-y-4 text-[15px] sm:grid-cols-2">
                        @foreach ([
                            'Full name' => $applicant->fullName(),
                            'Gender' => $applicant->gender->label(),
                            'Date of birth' => $applicant->date_of_birth ? $applicant->date_of_birth->format('j F Y').' ('.$applicant->date_of_birth->age.' years)' : null,
                            'Religion' => $applicant->religion,
                            'State of origin' => $applicant->state_of_origin,
                            'Current or last school' => $applicant->previous_school,
                            'Home address' => $applicant->address,
                        ] as $label => $value)
                            <div>
                                <dt class="text-sm text-muted">{{ $label }}</dt>
                                <dd class="m-0 mt-0.5 font-medium">{{ $value ?: '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-card>
            </x-tab>
            <x-tab name="parent">
                <x-card>
                    <dl class="m-0 grid grid-cols-1 gap-x-8 gap-y-4 text-[15px] sm:grid-cols-2">
                        @foreach ([
                            'Name' => $applicant->guardianName(),
                            'Relationship' => \App\Enums\GuardianRelationship::tryFrom($applicant->guardian_relationship)?->label(),
                            'Phone' => $applicant->guardian_phone,
                            'Email' => $applicant->guardian_email,
                            'Occupation' => $applicant->guardian_occupation,
                        ] as $label => $value)
                            <div>
                                <dt class="text-sm text-muted">{{ $label }}</dt>
                                <dd class="m-0 mt-0.5 font-medium">{{ $value ?: '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <div class="mt-5 flex flex-wrap gap-2 border-t border-line pt-4">
                        <x-button variant="soft" size="sm" icon="phone" :href="'tel:'.$applicant->guardian_phone">Call</x-button>
                        @if ($applicant->guardian_email)
                            <x-button variant="soft" size="sm" icon="mail" :href="'mailto:'.$applicant->guardian_email">Email</x-button>
                        @endif
                    </div>
                </x-card>
            </x-tab>
            <x-tab name="notes">
                <x-card>
                    <form method="POST" action="{{ route('school.admissions.update', $applicant) }}" class="flex flex-col gap-4">
                        @csrf
                        @method('PATCH')
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-field label="Entrance exam" name="exam_at" type="datetime-local" :value="$applicant->exam_at?->format('Y-m-d\TH:i')" />
                            <x-field label="Exam score (out of 100)" name="exam_score" type="number" step="0.5" min="0" max="100" inputmode="decimal" :value="$applicant->exam_score" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="notes" class="text-sm font-semibold">Notes</label>
                            <textarea id="notes" name="notes" rows="4" class="field resize-y" placeholder="Interview notes, documents seen, sibling at the school…">{{ old('notes', $applicant->notes) }}</textarea>
                        </div>
                        <x-button variant="secondary" class="self-start">Save</x-button>
                    </form>
                </x-card>
            </x-tab>
        </x-tabs>

        <div class="flex flex-col gap-5">
            @if ($applicant->status === ApplicantStatus::Enrolled && $applicant->student)
                <x-card>
                    <h2 class="m-0 text-base font-semibold">Enrolled</h2>
                    <p class="m-0 mt-1 text-[15px] text-muted">Now a student with admission number <span class="font-mono font-semibold text-ink">{{ $applicant->student->admission_no }}</span>.</p>
                    <x-button variant="soft" size="sm" icon="arrow-right" class="mt-4" :href="route('school.students.show', $applicant->student)">Open student record</x-button>
                </x-card>
            @endif

            @if ($applicant->status->canEnrol())
                <x-card>
                    <h2 class="m-0 text-base font-semibold">Enrol {{ $applicant->first_name }}</h2>
                    <p class="m-0 mt-1 text-sm text-muted">Gives an admission number, a place in {{ $applicant->academicSession->name }}, and logins for {{ $applicant->first_name }} and the parent.</p>
                    <form method="POST" action="{{ route('school.admissions.enrol', $applicant) }}" class="mt-4 flex flex-col gap-3">
                        @csrf
                        <x-select-field label="Class" name="class_arm_id" :options="$arms->mapWithKeys(fn ($arm) => [$arm->id => $arm->displayName().($arm->stream ? ' · '.$arm->stream->name : '')])->all()" placeholder="Choose a class" required />
                        <x-button variant="brand" icon="user-plus">Enrol as a student</x-button>
                    </form>
                </x-card>
            @endif

            @if ($nextSteps)
                <x-card>
                    <h2 class="m-0 text-base font-semibold">Next step</h2>
                    <form method="POST" action="{{ route('school.admissions.update', $applicant) }}" class="mt-3 flex flex-col gap-3" x-data="{ status: @js(old('status', '')) }">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="notes" value="{{ $applicant->notes }}">
                        <div class="flex flex-col gap-2" role="radiogroup" aria-label="Move the application to">
                            @foreach ($nextSteps as $step)
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line px-3.5 py-2.5 text-[15px] has-checked:border-brand-600 has-checked:bg-brand-50">
                                    <input type="radio" name="status" value="{{ $step->value }}" x-model="status" class="accent-brand-600">
                                    {{ $step->label() }}
                                </label>
                            @endforeach
                        </div>
                        <div x-show="status === @js(ApplicantStatus::ExamScheduled->value)" x-cloak>
                            <x-field label="Exam date and time" name="exam_at" id="next_exam_at" type="datetime-local" :value="$applicant->exam_at?->format('Y-m-d\TH:i')" />
                        </div>
                        @error('status')<p class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
                        <x-button class="self-start" x-bind:disabled="! status">Update</x-button>
                    </form>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.app>
