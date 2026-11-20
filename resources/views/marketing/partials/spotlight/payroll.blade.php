<div class="w-full overflow-hidden rounded-2xl bg-white text-sm">
    <div class="flex flex-wrap items-center justify-between gap-2.5 border-b border-line px-[18px] py-4">
        <div>
            <div class="text-xs text-muted">October 2026 payroll · 38 staff</div>
            <div class="text-[17px] font-bold">Net pay ₦6,240,000</div>
        </div>
        <span class="rounded-[10px] bg-ink px-3 py-2 text-[13px] font-semibold text-white">Send payslips</span>
    </div>
    <table class="w-full border-collapse text-left">
        <thead>
            <tr class="text-[11px] font-semibold tracking-[0.05em] text-muted">
                <th scope="col" class="w-[40%] px-[18px] py-2 font-semibold">STAFF</th>
                <th scope="col" class="py-2 font-semibold">GROSS</th>
                <th scope="col" class="py-2 font-semibold">DEDUCT.</th>
                <th scope="col" class="py-2 pr-[18px] font-semibold">NET</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($payroll as $row)
                <tr class="border-t border-line">
                    <th scope="row" class="px-[18px] py-2.5 text-left font-normal">
                        <span class="block font-semibold">{{ $row['name'] }}</span>
                        <span class="text-xs text-muted">{{ $row['role'] }}</span>
                    </th>
                    <td class="py-2.5">{{ $row['gross'] }}</td>
                    <td class="py-2.5 text-danger">{{ $row['deductions'] }}</td>
                    <td class="py-2.5 pr-[18px] font-bold">{{ $row['net'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
