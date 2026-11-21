@php
    use App\Support\Money;
@endphp

<x-layouts.app title="Expenses">
    <x-page-header kicker="Finance" title="Expenses" :description="Money::format($total).' spent in '.$period->label().' ('.$period->from->format('j M').' – '.$period->to->format('j M Y').').'">
        <x-slot:actions>
            <form method="GET">
                <label for="term" class="sr-only">Term</label>
                <select id="term" name="term" class="field w-60 bg-white py-2" x-data x-on:change="$el.form.requestSubmit()">
                    @foreach ($terms as $id => $label)<option value="{{ $id }}" @selected($period->term?->id === $id)>{{ $label }}</option>@endforeach
                </select>
            </form>
            <x-button variant="secondary" icon="wallet" :href="route('school.cashflow.index', ['term' => $period->term?->id])">Cashflow</x-button>
            @if ($canRecord)
                <x-button variant="brand" icon="plus" type="button" x-data x-on:click="$dispatch('open-drawer', 'new-expense')">Record an expense</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($byCategory->isNotEmpty())
        @php($top = max(1, (int) $byCategory->max('total')))
        <x-card>
            <h2 class="m-0 text-base font-semibold">Where the money went</h2>
            <ul class="m-0 mt-4 grid list-none grid-cols-1 gap-x-8 gap-y-2.5 p-0 md:grid-cols-2">
                @foreach ($byCategory as $row)
                    <li>
                        <a href="{{ route('school.expenses.index', ['term' => $period->term?->id, 'category' => $category === $row->category ? null : $row->category->value]) }}" @class(['grid grid-cols-[1.75rem_minmax(0,11rem)_minmax(0,1fr)_auto] items-center gap-3 rounded-lg px-1 py-0.5 text-sm hover:bg-canvas', 'bg-brand-50' => $category === $row->category])>
                            <x-dynamic-component :component="'lucide-'.$row->category->icon()" class="size-4 text-muted" aria-hidden="true" />
                            <span class="truncate">{{ $row->category->label() }}</span>
                            <span class="h-2 overflow-hidden rounded-full bg-chip"><span class="block h-full rounded-full bg-gold" style="width: {{ round($row->total / $top * 100) }}%"></span></span>
                            <span class="font-mono text-xs font-semibold">{{ Money::format((int) $row->total) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <x-card :padding="false">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3">
            <h2 class="m-0 text-base font-semibold">{{ $category ? $category->label() : 'All expenses' }}</h2>
            @if ($category)<a href="{{ route('school.expenses.index', ['term' => $period->term?->id]) }}" class="text-sm font-semibold text-brand-600 hover:text-ink">Show all</a>@endif
        </div>
        @if ($expenses->isEmpty())
            <x-empty-state icon="trending-down" title="No expenses recorded" body="Record diesel, repairs, salaries and other spending here, with a photo of the receipt." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] border-collapse text-left text-[15px]">
                    <thead>
                        <tr class="text-xs font-semibold tracking-[0.05em] text-muted uppercase">
                            <th scope="col" class="px-5 py-3">Date</th>
                            <th scope="col" class="px-3 py-3">What for</th>
                            <th scope="col" class="px-3 py-3">Paid to</th>
                            <th scope="col" class="px-3 py-3 text-right">Amount</th>
                            <th scope="col" class="px-5 py-3 text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($expenses as $expense)
                            <tr @class(['border-t border-line', 'opacity-60' => $expense->isVoid()])>
                                <td class="px-5 py-3 text-sm whitespace-nowrap">{{ $expense->spent_on->format('D j M') }}</td>
                                <td class="px-3 py-3">
                                    <span @class(['block font-medium', 'line-through' => $expense->isVoid()])>{{ $expense->description }}</span>
                                    <span class="block text-xs text-muted">{{ $expense->category->label() }} · {{ $expense->method->label() }}{{ $expense->reference ? ' · '.$expense->reference : '' }} · by {{ $expense->recorder?->name ?? 'the bursary' }}</span>
                                    @if ($expense->isVoid())<span class="block text-xs text-danger">Voided: {{ $expense->void_reason }}</span>@endif
                                </td>
                                <td class="px-3 py-3 text-sm">{{ $expense->paid_to ?? '—' }}</td>
                                <td class="px-3 py-3 text-right font-mono font-semibold whitespace-nowrap">{{ Money::format($expense->amount) }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex justify-end gap-1">
                                        @if ($expense->receipt)
                                            <a href="{{ route('school.expenses.receipt', $expense) }}" target="_blank" rel="noopener" class="grid size-9 place-items-center rounded-lg text-muted hover:bg-chip hover:text-ink" aria-label="Receipt for {{ $expense->description }}" title="Receipt"><x-lucide-paperclip class="size-4" aria-hidden="true" /></a>
                                        @endif
                                        @if ($canRecord && ! $expense->isVoid())
                                            <button type="button" class="grid size-9 place-items-center rounded-lg text-muted hover:bg-danger-50 hover:text-danger" aria-label="Void {{ $expense->description }}" title="Void" x-data x-on:click="$dispatch('edit-void', { action: @js(route('school.expenses.void', $expense)), values: { description: @js($expense->description), amount: @js(Money::format($expense->amount)) } })"><x-lucide-ban class="size-4" aria-hidden="true" /></button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-line px-5 py-3">{{ $expenses->links() }}</div>
        @endif
    </x-card>

    @if ($canRecord)
        <x-drawer name="new-expense" title="Record an expense" description="Keep the receipt: snap it with your phone and attach it here." :show="$errors->expense->isNotEmpty() || request()->boolean('new')" width="sm:max-w-lg">
            <form method="POST" action="{{ route('school.expenses.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4" id="new-expense-form">
                @csrf
                <x-select-field label="Category" name="category" :options="\App\Enums\ExpenseCategory::options()" placeholder="Choose" bag="expense" required />
                <x-field label="What was it for?" name="description" bag="expense" placeholder="e.g. 200 litres of diesel for the generator" required maxlength="150" />
                <div class="grid grid-cols-2 gap-4">
                    <x-field label="Amount (₦)" name="amount" bag="expense" inputmode="decimal" placeholder="45,000" required />
                    <x-field label="Date" name="spent_on" type="date" bag="expense" :value="old('spent_on', today()->toDateString())" required />
                </div>
                <x-field label="Paid to" name="paid_to" bag="expense" placeholder="e.g. Total filling station, Wuse" />
                <div class="grid grid-cols-2 gap-4">
                    <x-select-field label="Paid by" name="method" :options="$methods" value="cash" bag="expense" required />
                    <x-field label="Reference" name="reference" bag="expense" placeholder="Cheque or transfer no." />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="expense-receipt" class="text-sm font-semibold">Receipt <span class="font-normal text-muted">(optional)</span></label>
                    <input id="expense-receipt" name="receipt" type="file" accept="image/*,application/pdf" class="field py-2 text-sm">
                    @error('receipt', 'expense')<p class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
                </div>
            </form>
            <x-slot:footer><x-button variant="brand" icon="check" form="new-expense-form">Record expense</x-button></x-slot:footer>
        </x-drawer>

        <div x-data="editPanel('void')">
            <x-drawer name="void" title="Void this expense" title-expression="'Void ' + (values.amount ?? '')" description="It stays in the books, crossed out, and stops counting in the totals.">
                <form method="POST" :action="action" class="flex flex-col gap-4">
                    @csrf
                    <p class="m-0 text-[15px]" x-text="values.description"></p>
                    <div class="flex flex-col gap-1.5">
                        <label for="void-reason" class="text-sm font-semibold">Why?</label>
                        <input id="void-reason" name="reason" class="field" required maxlength="255" placeholder="e.g. Entered twice">
                        @error('reason', 'voidExpense')<p class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
                    </div>
                    <x-button variant="danger" class="self-start" icon="ban">Void expense</x-button>
                </form>
            </x-drawer>
        </div>
    @endif
</x-layouts.app>
