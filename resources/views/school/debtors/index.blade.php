@php
    use App\Support\Money;
@endphp

<x-layouts.app title="Debtors">
    <x-page-header kicker="Finance" title="Debtors" :description="$rows->count().' '.str('student')->plural($rows->count()).' owe '.Money::format($total).' in total.'">
        <x-slot:actions>
            <x-button data-native variant="secondary" icon="download" :href="route('school.debtors.index', array_filter(['class' => $armId, 'export' => 'csv']))">Download CSV</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-line px-5 py-4">
            <div class="flex w-48 flex-col gap-1.5">
                <label for="class" class="text-sm font-semibold">Class</label>
                <select id="class" name="class" class="field" x-data x-on:change="$el.form.requestSubmit()">
                    <option value="">All classes</option>
                    @foreach ($arms as $id => $name)<option value="{{ $id }}" @selected($armId === $id)>{{ $name }}</option>@endforeach
                </select>
            </div>
        </form>

        @if ($rows->isEmpty())
            <x-empty-state icon="hand-coins" title="Nobody owes the school" body="Students with unpaid or part-paid invoices appear here, largest balance first." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] border-collapse text-left text-[15px]">
                    <thead>
                        <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                            <th scope="col" class="px-5 py-3 font-semibold">Student</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Class</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Parent</th>
                            <th scope="col" class="px-3 py-3 text-right font-semibold">Invoices</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Oldest due</th>
                            <th scope="col" class="px-5 py-3 text-right font-semibold">Owes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php($guardian = $row['student']->guardians->first())
                            @php($due = $row['oldest_due'] ? \Illuminate\Support\Carbon::parse($row['oldest_due']) : null)
                            <tr class="border-t border-line hover:bg-canvas">
                                <td class="px-5 py-3">
                                    <a href="{{ route('school.students.show', $row['student']).'#fees' }}" class="font-medium hover:text-brand-600">{{ $row['student']->registerName() }}</a>
                                    <span class="block font-mono text-xs text-muted">{{ $row['student']->admission_no }}</span>
                                </td>
                                <td class="px-3 py-3">{{ $row['student']->currentEnrolment?->classArm->displayName() ?? '—' }}</td>
                                <td class="px-3 py-3 text-sm">
                                    {{ $guardian?->fullName() ?? '—' }}
                                    @if ($guardian)<a href="tel:{{ $guardian->phone }}" class="block font-mono text-xs text-brand-600">{{ $guardian->phone }}</a>@endif
                                </td>
                                <td class="px-3 py-3 text-right">{{ $row['invoices'] }}</td>
                                <td @class(['px-3 py-3 text-sm', 'font-semibold text-danger' => $due?->isPast()])>{{ $due?->format('j M Y') ?? '—' }}</td>
                                <td class="px-5 py-3 text-right font-bold">{{ Money::format($row['owed']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts.app>
