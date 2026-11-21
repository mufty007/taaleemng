{{-- Fees in against spending out this term. Expects $cashflow. --}}
@use('App\Support\Money')

@if ($cashflow)
    <x-card>
        <div class="flex items-center justify-between">
            <h2 class="m-0 text-base font-semibold">Cashflow this term</h2>
            <a href="{{ route('school.cashflow.index') }}" class="text-sm font-semibold text-brand-600 hover:text-ink">Details</a>
        </div>
        <dl class="m-0 mt-3 grid grid-cols-3 gap-2 text-center">
            <div class="rounded-xl bg-naija-50 px-2 py-3"><dt class="text-xs text-naija-700">In</dt><dd class="m-0 text-base font-bold">{{ Money::compact($cashflow['in']) }}</dd></div>
            <div class="rounded-xl bg-danger-50 px-2 py-3"><dt class="text-xs text-danger">Out</dt><dd class="m-0 text-base font-bold">{{ Money::compact($cashflow['out']) }}</dd></div>
            <div class="rounded-xl bg-brand-50 px-2 py-3"><dt class="text-xs text-brand-700">Left</dt><dd @class(['m-0 text-base font-bold', 'text-danger' => $cashflow['in'] < $cashflow['out']])>{{ Money::compact($cashflow['in'] - $cashflow['out']) }}</dd></div>
        </dl>
        @can('expenses.manage')
            <x-button variant="soft" size="sm" icon="plus" class="mt-3 w-full" :href="route('school.expenses.index').'?new=1'">Record an expense</x-button>
        @endcan
    </x-card>
@endif
