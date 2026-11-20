<div class="flex w-full flex-col gap-4 rounded-2xl bg-white p-5">
    <div class="flex flex-wrap items-center justify-between gap-2.5">
        <div>
            <div class="text-xs text-muted">SS 3 Mock Exam · Mathematics</div>
            <div class="text-base font-bold">Question 12 of 50</div>
        </div>
        <span class="rounded-[10px] bg-ink px-3 py-[7px] text-sm font-bold text-white">38:15 left</span>
    </div>
    <div class="h-1.5 overflow-hidden rounded-full bg-[#EEF0F8]" role="progressbar" aria-valuenow="24" aria-valuemin="0" aria-valuemax="100" aria-label="Exam progress">
        <div class="h-full w-[24%] bg-brand"></div>
    </div>
    <div class="text-lg font-semibold" id="cbt-question">Simplify 2/3 + 3/4.</div>
    <div class="flex flex-col gap-2" role="radiogroup" aria-labelledby="cbt-question">
        @foreach (['A' => '17/12', 'B' => '5/7', 'C' => '6/12', 'D' => '1 1/12'] as $key => $option)
            <button
                type="button"
                role="radio"
                @click="answer = '{{ $key }}'"
                :aria-checked="(answer === '{{ $key }}').toString()"
                :class="answer === '{{ $key }}' ? 'border-brand-600 bg-brand-50' : 'border-line bg-white hover:border-brand'"
                class="flex items-center gap-3 rounded-xl border-[1.5px] px-3.5 py-[11px] text-left text-[15px] text-ink transition"
            >
                <span
                    :class="answer === '{{ $key }}' ? 'bg-brand-600 text-white' : 'bg-chip text-ink'"
                    class="grid size-7 flex-none place-items-center rounded-lg text-[13px] font-bold"
                >{{ $key }}</span>
                {{ $option }}
            </button>
        @endforeach
    </div>
    <div class="text-[13px] text-muted" aria-live="polite" x-text="answer ? `Answer ${answer} saved. Students can change it until time runs out.` : 'Pick an answer to try it.'">Pick an answer to try it.</div>
</div>
