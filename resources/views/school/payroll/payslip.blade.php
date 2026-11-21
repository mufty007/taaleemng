@php
    use App\Support\Money;
@endphp

<x-layouts.print :title="'Payslip '.$line->staff_name" document="Payslip" :school="$school" page="A5" width="max-w-[640px]">
    <x-slot:meta>
        <div class="mt-1.5 text-lg font-bold">{{ $run->label() }}</div>
        <div class="text-sm text-muted">{{ $line->staff_no }}</div>
    </x-slot:meta>

    <section class="grid grid-cols-2 gap-x-8 gap-y-3 py-5 text-[15px]">
        <div>
            <div class="text-sm text-muted">Employee</div>
            <div class="font-semibold">{{ $line->staff_name }}</div>
        </div>
        <div>
            <div class="text-sm text-muted">Designation</div>
            <div class="font-semibold">{{ $line->designation }}</div>
        </div>
        <div>
            <div class="text-sm text-muted">Bank</div>
            <div class="font-semibold">{{ $line->bank_name ? $line->bank_name.($line->bank_account_number ? ' · '.$line->bank_account_number : '') : '—' }}</div>
        </div>
        <div>
            <div class="text-sm text-muted">Account name</div>
            <div class="font-semibold">{{ $line->bank_account_name ?? '—' }}</div>
        </div>
    </section>

    <table class="w-full border-collapse text-left text-[15px]">
        <tbody>
            <tr class="border-b border-line">
                <td class="py-2.5 text-muted">Basic salary</td>
                <td class="py-2.5 text-right font-mono font-semibold">{{ Money::format($line->basic_salary) }}</td>
            </tr>
            <tr class="border-b border-line">
                <td class="py-2.5 text-muted">Allowances</td>
                <td class="py-2.5 text-right font-mono font-semibold">{{ Money::format($line->allowances) }}</td>
            </tr>
            <tr class="border-b border-line">
                <td class="py-2.5 font-medium">Gross</td>
                <td class="py-2.5 text-right font-mono font-semibold">{{ Money::format($line->gross()) }}</td>
            </tr>
            <tr class="border-b border-line">
                <td class="py-2.5 text-muted">Deductions</td>
                <td class="py-2.5 text-right font-mono font-semibold">{{ Money::format($line->deductions) }}</td>
            </tr>
            <tr>
                <td class="py-3 text-lg font-bold">Net pay</td>
                <td class="py-3 text-right text-2xl font-bold text-brand-700">{{ Money::format($line->net_pay) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="mt-4 rounded-xl bg-brand-50 px-4 py-3 text-[15px]">
        <span class="text-sm text-muted">The sum of</span>
        <span class="font-semibold">{{ Money::inWords($line->net_pay) }}</span>
    </div>

    <div class="mt-8 flex items-end justify-between gap-6 text-sm text-muted">
        <span>{{ $line->isPaid() ? 'Paid '.$line->paid_at->format('j M Y') : 'Prepared — awaiting payment' }}</span>
        <span class="w-48 border-t border-dashed border-line pt-1 text-center">Bursar’s signature &amp; stamp</span>
    </div>
</x-layouts.print>
