{{-- This term's collections against bills, the last eight weeks, and how parents pay. Expects $fees. --}}
@use('App\Support\Money')
@php($maxWeek = max(1, collect($fees['weekly'])->max('amount')))

<x-card :padding="false">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
        <div>
            <h2 class="m-0 text-base font-semibold">School fees this term</h2>
            <p class="m-0 text-sm text-muted">{{ Money::format($fees['collected']) }} of {{ Money::format($fees['billed']) }} billed</p>
        </div>
        <a href="{{ route('school.debtors.index') }}" class="text-sm font-semibold text-brand-600 hover:text-ink">Debtors</a>
    </div>
    <div class="flex flex-col gap-5 p-5">
        <div>
            <div class="flex items-center justify-between text-sm"><span class="font-semibold">{{ $fees['rate'] ?? 0 }}% collected</span><span class="text-muted">{{ Money::format(max(0, $fees['billed'] - $fees['collected'])) }} still to come</span></div>
            <div class="mt-2 h-3 overflow-hidden rounded-full bg-chip" role="progressbar" aria-valuenow="{{ $fees['rate'] ?? 0 }}" aria-valuemin="0" aria-valuemax="100" aria-label="Share of this term’s fees collected">
                <div class="h-full rounded-full bg-naija" style="width: {{ min(100, $fees['rate'] ?? 0) }}%"></div>
            </div>
        </div>

        <div>
            <h3 class="m-0 text-sm font-semibold text-muted">Collected each week</h3>
            <div class="mt-3 grid h-32 grid-cols-8 items-end gap-2" role="img" aria-label="Fees collected in each of the last eight weeks">
                @foreach ($fees['weekly'] as $week)
                    <div class="flex h-full flex-col items-center justify-end gap-1.5" title="Week of {{ $week['label'] }}: {{ Money::format($week['amount']) }}">
                        <span class="text-[10px] font-semibold text-muted">{{ $week['amount'] ? Money::compact($week['amount']) : '' }}</span>
                        <span @class(['w-full rounded-t-md', 'bg-brand' => ! $loop->last, 'bg-brand-600' => $loop->last]) style="height: {{ max(2, round($week['amount'] / $maxWeek * 100)) }}%"></span>
                        <span class="text-[10px] whitespace-nowrap text-muted">{{ $week['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        @if ($fees['methods'])
            @php($methodTotal = max(1, array_sum($fees['methods'])))
            <div>
                <h3 class="m-0 text-sm font-semibold text-muted">How parents paid this term</h3>
                <ul class="m-0 mt-2 flex list-none flex-col gap-1.5 p-0 text-sm">
                    @foreach ($fees['methods'] as $method => $amount)
                        <li class="grid grid-cols-[88px_minmax(0,1fr)_auto] items-center gap-3">
                            <span>{{ $method }}</span>
                            <span class="h-2 overflow-hidden rounded-full bg-chip"><span class="block h-full rounded-full bg-gold" style="width: {{ round($amount / $methodTotal * 100) }}%"></span></span>
                            <span class="font-mono text-xs">{{ Money::format($amount) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-card>
