<div class="w-full overflow-hidden rounded-2xl bg-white text-sm">
    <div class="flex flex-wrap justify-between gap-2.5 border-b border-line px-[18px] py-4">
        <div>
            <div class="text-xs text-muted">First Term Report · 2026/2027</div>
            <div class="text-[17px] font-bold">Aisha Bello · JSS 2A</div>
        </div>
        <div class="text-right">
            <div class="text-xs text-muted">Position</div>
            <div class="text-[17px] font-bold text-brand-600">1st of 42</div>
        </div>
    </div>
    <table class="w-full border-collapse text-left">
        <thead>
            <tr class="text-[11px] font-semibold tracking-[0.05em] text-muted">
                <th scope="col" class="w-[40%] px-[18px] py-2 font-semibold">SUBJECT</th>
                <th scope="col" class="py-2 font-semibold">CA</th>
                <th scope="col" class="py-2 font-semibold">EXAM</th>
                <th scope="col" class="py-2 font-semibold">TOTAL</th>
                <th scope="col" class="py-2 pr-[18px] font-semibold">GRADE</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reportRows as $row)
                @php($total = $row['ca'] + $row['exam'])
                <tr class="border-t border-line">
                    <th scope="row" class="px-[18px] py-[9px] font-medium">{{ $row['subject'] }}</th>
                    <td class="py-[9px]">{{ $row['ca'] }}</td>
                    <td class="py-[9px]">{{ $row['exam'] }}</td>
                    <td class="py-[9px] font-bold">{{ $total }}</td>
                    <td class="py-[9px] pr-[18px] font-semibold text-brand-600">{{ \App\Support\Marketing\LandingContent::waecGrade($total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="border-t border-line bg-surface px-[18px] py-3 text-[13px] text-muted">
        <b class="text-ink">Principal’s remark:</b> An excellent term. Keep it up, Aisha.
    </div>
</div>
