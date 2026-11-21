@php
    use App\Support\Money;
@endphp

<x-layouts.print :title="$run->label().' payroll'" document="Payroll sheet" :school="$school" page="A4 landscape" width="max-w-[1100px]">
    <x-slot:meta>
        <div class="mt-1.5 text-lg font-bold">{{ $run->label() }}</div>
        <div class="text-sm text-muted">{{ $run->headcount }} staff · {{ Money::format($run->net_total) }} net</div>
    </x-slot:meta>

    <table class="mt-4 w-full border-collapse text-left text-sm">
        <thead>
            <tr class="border-b-2 border-brand-200 text-xs font-semibold text-brand-700 uppercase">
                <th scope="col" class="py-2 pr-2">#</th>
                <th scope="col" class="py-2 pr-2">Staff</th>
                <th scope="col" class="py-2 pr-2">Designation</th>
                <th scope="col" class="py-2 pr-2">Bank</th>
                <th scope="col" class="py-2 pr-2 text-right">Basic</th>
                <th scope="col" class="py-2 pr-2 text-right">Allow.</th>
                <th scope="col" class="py-2 pr-2 text-right">Deduct.</th>
                <th scope="col" class="py-2 text-right">Net</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($run->lines as $index => $line)
                <tr class="border-b border-line">
                    <td class="py-2 pr-2 text-muted">{{ $index + 1 }}</td>
                    <td class="py-2 pr-2 font-medium">{{ $line->staff_name }} <span class="font-mono text-xs text-muted">{{ $line->staff_no }}</span></td>
                    <td class="py-2 pr-2">{{ $line->designation }}</td>
                    <td class="py-2 pr-2 text-xs">{{ $line->bank_account_number ? ($line->bank_name.' '.$line->bank_account_number) : '—' }}</td>
                    <td class="py-2 pr-2 text-right font-mono">{{ Money::format($line->basic_salary) }}</td>
                    <td class="py-2 pr-2 text-right font-mono">{{ Money::format($line->allowances) }}</td>
                    <td class="py-2 pr-2 text-right font-mono">{{ Money::format($line->deductions) }}</td>
                    <td class="py-2 text-right font-mono font-semibold">{{ Money::format($line->net_pay) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="border-t-2 border-brand-200 font-semibold">
                <td class="py-3" colspan="4">Totals</td>
                <td class="py-3 text-right font-mono">{{ Money::format($run->lines->sum('basic_salary')) }}</td>
                <td class="py-3 text-right font-mono">{{ Money::format($run->lines->sum('allowances')) }}</td>
                <td class="py-3 text-right font-mono">{{ Money::format($run->deductions_total) }}</td>
                <td class="py-3 text-right font-mono text-brand-700">{{ Money::format($run->net_total) }}</td>
            </tr>
        </tfoot>
    </table>

    <x-slot:footer>
        Status: {{ $run->status->label() }}{{ $run->paid_at ? ' · '.$run->paid_at->format('j M Y') : '' }}
    </x-slot:footer>
</x-layouts.print>
