@php
    use App\Support\Money;
@endphp

<x-layouts.app title="Payroll">
    <x-page-header kicker="Finance" title="Payroll" description="Prepare monthly pay from salaries on each staff record, mark paid, and print payslips.">
        <x-slot:actions>
            <form method="GET">
                <label for="year" class="sr-only">Year</label>
                <select id="year" name="year" class="field w-32 bg-white py-2" x-data x-on:change="$el.form.requestSubmit()">
                    @foreach ($years as $option)
                        <option value="{{ $option }}" @selected($year === (int) $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </form>
            @if ($canManage && $withSalary > 0)
                <x-button variant="brand" icon="plus" type="button" x-data x-on:click="$dispatch('open-drawer', 'new-payroll')">Prepare payroll</x-button>
            @elseif ($canManage)
                <x-button variant="brand" icon="briefcase" :href="route('school.staff.index')">Set staff salaries</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($withSalary === 0)
        <x-card>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="m-0 text-base font-semibold">Set salaries before preparing payroll</h2>
                    <p class="m-0 mt-1 text-sm text-muted">Open each staff record, edit, and fill in basic salary (plus allowances and deductions if you use them).</p>
                </div>
                <x-button variant="soft" icon="briefcase" :href="route('school.staff.index')">Open staff list</x-button>
            </div>
        </x-card>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-stat-card label="Staff with salary" :value="(string) $withSalary" icon="banknote" tone="naija" hint="Included when you prepare a run" />
        <x-stat-card label="No salary set" :value="(string) $withoutSalary" icon="user-round-x" tone="gold" hint="Skipped until you set basic salary" :href="route('school.staff.index')" />
    </div>

    <x-card :padding="false">
        <div class="border-b border-line px-5 py-3">
            <h2 class="m-0 text-base font-semibold">{{ $year }} runs</h2>
        </div>
        @if ($runs->isEmpty())
            <x-empty-state icon="banknote" title="No payroll yet this year" body="Set basic salaries on staff records, then prepare a month’s payroll here." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] border-collapse text-left text-[15px]">
                    <thead>
                        <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                            <th scope="col" class="px-5 py-3">Month</th>
                            <th scope="col" class="px-3 py-3">Staff</th>
                            <th scope="col" class="px-3 py-3 text-right">Net pay</th>
                            <th scope="col" class="px-3 py-3">Status</th>
                            <th scope="col" class="px-5 py-3"><span class="sr-only">Open</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($runs as $run)
                            <tr @class(['border-t border-line', 'opacity-60' => $run->isVoid()])>
                                <td class="px-5 py-3 font-semibold">{{ $run->label() }}</td>
                                <td class="px-3 py-3 text-sm text-muted">{{ $run->headcount }} · by {{ $run->preparer?->name ?? 'the bursary' }}</td>
                                <td class="px-3 py-3 text-right font-mono font-semibold">{{ Money::format($run->net_total) }}</td>
                                <td class="px-3 py-3"><x-badge :tone="$run->status->tone()">{{ $run->status->label() }}</x-badge></td>
                                <td class="px-5 py-3 text-right">
                                    <x-button variant="ghost" size="sm" icon="arrow-right" :href="route('school.payroll.show', $run)">Open</x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    @if ($canManage)
        <x-drawer name="new-payroll" title="Prepare payroll" description="Pulls active staff who have a basic salary. Amounts are snapped for this month." :show="$errors->isNotEmpty() || request()->boolean('new')" width="sm:max-w-md">
            <form method="POST" action="{{ route('school.payroll.store') }}" class="flex flex-col gap-4">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <x-select-field label="Month" name="month" :options="$monthOptions" :value="old('month', (string) now()->month)" required />
                <x-button variant="brand" size="lg" icon="check">Prepare {{ $year }} payroll</x-button>
            </form>
        </x-drawer>
    @endif
</x-layouts.app>
