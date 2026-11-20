<div class="flex w-full flex-col gap-3">
    <div class="grid grid-cols-2 gap-3">
        <div class="rounded-[14px] bg-white p-4">
            <div class="text-xs text-muted">Students present today</div>
            <div class="text-[28px] font-bold tracking-[-0.02em]">96%</div>
            <div class="text-xs font-semibold text-brand-600">612 of 638</div>
        </div>
        <div class="rounded-[14px] bg-white p-4">
            <div class="text-xs text-muted">Staff clocked in by 7:45</div>
            <div class="text-[28px] font-bold tracking-[-0.02em]">41 / 43</div>
            <div class="text-xs font-semibold text-danger">2 late arrivals</div>
        </div>
    </div>
    <figure class="m-0 flex flex-col gap-3 rounded-[14px] bg-white p-4">
        <figcaption class="text-sm font-semibold">This week · whole school</figcaption>
        <div class="flex h-[150px] items-end gap-3">
            @foreach ($attendanceWeek as $day)
                <div class="flex h-full flex-1 flex-col items-center justify-end gap-2">
                    <span class="text-xs font-semibold">{{ $day['percent'] }}%</span>
                    <div
                        @class(['w-full rounded-[10px]', 'bg-brand' => $day['percent'] >= 95, 'bg-brand-100' => $day['percent'] < 95])
                        style="height: {{ ($day['percent'] - 70) / 30 * 100 }}%"
                        aria-hidden="true"
                    ></div>
                    <span class="text-xs text-muted">{{ $day['day'] }}</span>
                </div>
            @endforeach
        </div>
    </figure>
</div>
