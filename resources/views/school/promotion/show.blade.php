@php
    use App\Enums\PromotionDecision;
    use App\Support\Results\ResultCalculator;
@endphp

<x-layouts.app :title="'Promote '.$arm->displayName()">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.promotion.index', ['from' => $from->id]) }}" class="hover:text-ink">Promotion</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">{{ $arm->displayName() }}</span>
    </div>

    <x-page-header :kicker="$from->name.' → '.$to->name" :title="'Promote '.$arm->displayName()" :description="'Decisions come from the '.$finalTerm->name.' report cards. Change any before moving the class.'">
        <x-slot:actions>
            <x-button variant="brand" icon="trending-up" form="promotion-form">Move class into {{ $to->name }}</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($errors->any())
        <div class="rounded-2xl bg-danger-50 px-5 py-3 text-[15px] font-medium text-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <x-card :padding="false">
        @if ($rows->isEmpty())
            <x-empty-state icon="users" title="No students in this class" />
        @else
            <form id="promotion-form" method="POST" action="{{ route('school.promotion.store', [$arm, 'from' => $from->id]) }}">
                @csrf
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] border-collapse text-left text-[15px]">
                        <thead>
                            <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                                <th scope="col" class="w-10 px-4 py-3 font-semibold">#</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Student</th>
                                <th scope="col" class="px-3 py-3 text-right font-semibold">Session avg.</th>
                                <th scope="col" class="px-3 py-3 text-right font-semibold">Pos.</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Decision</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Moves into</th>
                                <th scope="col" class="px-4 py-3 font-semibold">In {{ $to->name }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $index => $row)
                                @php($enrolmentId = $row['enrolment']->id)
                                <tr
                                    class="border-t border-line"
                                    x-data="{ decision: @js(old("students.{$enrolmentId}.decision", $row['decision']->value)), up: @js($upArm?->id), same: @js($arm->id) }"
                                >
                                    <td class="px-4 py-2.5 text-sm text-muted">{{ $index + 1 }}</td>
                                    <th scope="row" class="px-3 py-2.5 text-left font-medium">
                                        {{ $row['enrolment']->student->registerName() }}
                                        <span class="block font-mono text-xs font-normal text-muted">{{ $row['enrolment']->student->admission_no }}</span>
                                    </th>
                                    <td class="px-3 py-2.5 text-right font-semibold">{{ $row['average'] ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-right text-brand-600">{{ $row['position'] ? ResultCalculator::ordinal($row['position']) : '—' }}</td>
                                    <td class="px-3 py-2.5">
                                        <label for="decision-{{ $enrolmentId }}" class="sr-only">Decision for {{ $row['enrolment']->student->fullName() }}</label>
                                        <select
                                            id="decision-{{ $enrolmentId }}"
                                            name="students[{{ $enrolmentId }}][decision]"
                                            class="field py-2"
                                            x-model="decision"
                                            x-on:change="if (decision !== 'graduated') { $refs.arm.value = decision === 'repeat' ? same : (up ?? same) }"
                                        >
                                            @foreach ($decisionOptions as $value => $label)<option value="{{ $value }}" @selected(old("students.{$enrolmentId}.decision", $row['decision']->value) === $value)>{{ $label }}</option>@endforeach
                                        </select>
                                        @if (! $row['recorded'])
                                            <span class="mt-1 block text-xs text-muted">Suggested</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5">
                                        @php($defaultArm = old("students.{$enrolmentId}.class_arm_id", $row['placed_arm_id'] ?? ($row['decision'] === PromotionDecision::Repeat ? $arm->id : ($upArm?->id ?? $arm->id))))
                                        <label for="arm-{{ $enrolmentId }}" class="sr-only">Class next session for {{ $row['enrolment']->student->fullName() }}</label>
                                        <select id="arm-{{ $enrolmentId }}" name="students[{{ $enrolmentId }}][class_arm_id]" class="field py-2" x-ref="arm" x-show="decision !== 'graduated'" x-bind:disabled="decision === 'graduated'">
                                            @foreach ($armOptions as $armId => $armName)<option value="{{ $armId }}" @selected((int) $defaultArm === $armId)>{{ $armName }}</option>@endforeach
                                        </select>
                                        <span x-show="decision === 'graduated'" x-cloak class="text-sm text-muted">Leaves the school</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-sm">
                                        @if ($row['placed_arm_id'])
                                            <x-badge tone="naija">{{ $armOptions[$row['placed_arm_id']] ?? 'Placed' }}</x-badge>
                                        @elseif ($row['enrolment']->status === \App\Enums\EnrolmentStatus::Graduated)
                                            <x-badge tone="brand">Graduated</x-badge>
                                        @else
                                            <span class="text-muted">Not yet</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-4">
                    <p class="m-0 text-sm text-muted">Promoted students go to {{ $upArm?->displayName() ?? 'the class you choose' }} unless you pick another class. Repeaters stay in {{ $arm->displayName() }}.</p>
                    <x-button variant="brand" icon="trending-up">Move class into {{ $to->name }}</x-button>
                </div>
            </form>
        @endif
    </x-card>
</x-layouts.app>
