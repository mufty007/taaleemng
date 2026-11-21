@if ($recentPayments->isNotEmpty())
    <x-card :padding="false">
        <div class="flex items-center justify-between border-b border-line px-5 py-4">
            <h2 class="m-0 text-base font-semibold">Latest payments</h2>
            <a href="{{ route('school.payments.index') }}" class="text-sm font-semibold text-brand-600 hover:text-ink">All payments</a>
        </div>
        <ul class="m-0 list-none p-0">
            @foreach ($recentPayments as $payment)
                <li class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-b-0">
                    <span class="grid size-8 flex-none place-items-center rounded-full bg-naija-50 text-naija"><x-lucide-banknote-arrow-down class="size-4" aria-hidden="true" /></span>
                    <div class="min-w-0 flex-1 text-sm">
                        <a href="{{ route('school.students.show', $payment->student) }}" class="block truncate font-semibold hover:text-brand-600">{{ $payment->student->fullName() }}</a>
                        <span class="text-xs text-muted">{{ $payment->method->label() }} · {{ $payment->paid_at?->diffForHumans() }}</span>
                    </div>
                    <span class="font-mono text-sm font-semibold">{{ \App\Support\Money::format($payment->amount) }}</span>
                </li>
            @endforeach
        </ul>
    </x-card>
@endif
