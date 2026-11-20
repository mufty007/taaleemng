<div class="flex w-full flex-col gap-2.5">
    <div class="flex flex-col gap-2.5 rounded-[14px] bg-white p-4">
        <div class="flex justify-between text-sm font-semibold"><span>First Term collection</span><span class="text-muted">77%</span></div>
        <div class="h-2.5 overflow-hidden rounded-full bg-[#EEF0F8]" role="progressbar" aria-valuenow="77" aria-valuemin="0" aria-valuemax="100" aria-label="First Term fees collected">
            <div class="h-full w-[77%] rounded-full bg-naija"></div>
        </div>
        <div class="flex justify-between text-[13px] text-muted">
            <span>Collected <b class="text-ink">₦14,120,000</b></span>
            <span>Owing <b class="text-ink">₦4,330,000</b></span>
        </div>
    </div>
    @foreach ($invoices as $invoice)
        @php($statusTone = ['Paid' => 'naija', 'Owing' => 'danger'][$invoice['status']] ?? 'gold')
        <div class="flex items-center gap-3 rounded-[14px] bg-white px-4 py-[13px]">
            <div class="min-w-0 flex-1">
                <div class="text-[15px] font-semibold">{{ $invoice['name'] }}</div>
                <div class="text-xs text-muted">{{ $invoice['detail'] }}</div>
            </div>
            <span class="font-semibold">{{ $invoice['amount'] }}</span>
            <x-badge :tone="$statusTone">{{ $invoice['status'] }}</x-badge>
        </div>
    @endforeach
</div>
