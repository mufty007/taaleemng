@php
    use App\Support\Money;
@endphp

<x-layouts.app :title="$run->label().' payroll'">
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
        <a href="{{ route('school.payroll.index') }}" class="hover:text-ink">Payroll</a>
        <x-lucide-chevron-right class="size-4" aria-hidden="true" />
        <span class="text-ink">{{ $run->label() }}</span>
    </div>

    <x-page-header kicker="Finance" :title="$run->label().' payroll'" :description="$run->headcount.' staff · prepared by '.($run->preparer?->name ?? 'the bursary').($run->isVoid() ? ' · Voided: '.$run->void_reason : '')">
        <x-slot:actions>
            <x-badge :tone="$run->status->tone()">{{ $run->status->label() }}</x-badge>
            <x-button variant="secondary" icon="printer" :href="route('school.payroll.sheet', $run)" target="_blank">Print sheet</x-button>
            @if ($canManage && ! $run->isVoid() && ! $run->isPaid())
                <form method="POST" action="{{ route('school.payroll.paid', $run) }}">
                    @csrf
                    <x-button variant="brand" icon="check">Mark all paid</x-button>
                </form>
                <x-button variant="ghost" icon="ban" type="button" x-data x-on:click="$dispatch('open-drawer', 'void-payroll')">Void</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat-card label="Gross" :value="Money::format($run->gross_total)" icon="arrow-up-right" tone="brand" />
        <x-stat-card label="Deductions" :value="Money::format($run->deductions_total)" icon="minus" tone="gold" />
        <x-stat-card label="Net to pay" :value="Money::format($run->net_total)" icon="banknote" tone="naija" />
    </div>

    <x-card :padding="false">
        <div class="border-b border-line px-5 py-3">
            <h2 class="m-0 text-base font-semibold">Payslips</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] border-collapse text-left text-[15px]">
                <thead>
                    <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                        <th scope="col" class="px-5 py-3">Staff</th>
                        <th scope="col" class="px-3 py-3 text-right">Basic</th>
                        <th scope="col" class="px-3 py-3 text-right">Allowances</th>
                        <th scope="col" class="px-3 py-3 text-right">Deductions</th>
                        <th scope="col" class="px-3 py-3 text-right">Net</th>
                        <th scope="col" class="px-5 py-3 text-right">Pay</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($run->lines as $line)
                        <tr class="border-t border-line">
                            <td class="px-5 py-3">
                                <span class="block font-semibold">{{ $line->staff_name }}</span>
                                <span class="block text-xs text-muted">{{ $line->designation }} · {{ $line->staff_no }}{{ $line->bank_account_number ? ' · '.$line->bank_name.' '.$line->bank_account_number : '' }}</span>
                            </td>
                            <td class="px-3 py-3 text-right font-mono text-sm">{{ Money::format($line->basic_salary) }}</td>
                            <td class="px-3 py-3 text-right font-mono text-sm">{{ Money::format($line->allowances) }}</td>
                            <td class="px-3 py-3 text-right font-mono text-sm">{{ Money::format($line->deductions) }}</td>
                            <td class="px-3 py-3 text-right font-mono font-semibold">{{ Money::format($line->net_pay) }}</td>
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-1">
                                    <a href="{{ route('school.payroll.payslip', [$run, $line]) }}" target="_blank" rel="noopener" class="grid size-9 place-items-center rounded-lg text-muted hover:bg-chip hover:text-ink" title="Payslip" aria-label="Payslip for {{ $line->staff_name }}"><x-lucide-printer class="size-4" aria-hidden="true" /></a>
                                    @if ($canManage && ! $run->isVoid() && ! $line->isPaid())
                                        <form method="POST" action="{{ route('school.payroll.line-paid', [$run, $line]) }}">
                                            @csrf
                                            <button type="submit" class="grid size-9 place-items-center rounded-lg text-muted hover:bg-naija-50 hover:text-naija-700" title="Mark paid" aria-label="Mark {{ $line->staff_name }} paid"><x-lucide-check class="size-4" aria-hidden="true" /></button>
                                        </form>
                                    @elseif ($line->isPaid())
                                        <x-badge tone="naija">Paid</x-badge>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>

    @if ($canManage && ! $run->isVoid())
        <x-drawer name="void-payroll" title="Void this payroll" description="The run stays on record but you can prepare {{ $run->label() }} again." :show="$errors->voidPayroll->isNotEmpty()" width="sm:max-w-md">
            <form method="POST" action="{{ route('school.payroll.void', $run) }}" class="flex flex-col gap-4">
                @csrf
                <x-field label="Reason" name="reason" bag="voidPayroll" placeholder="e.g. Prepared before salary reviews" required />
                <x-button variant="danger" size="lg" icon="ban">Void payroll</x-button>
            </form>
        </x-drawer>
    @endif
</x-layouts.app>
