{{-- "Record a payment" slide-in panel. Expects $student, $methods (value => label) and optionally $suggestedAmount (kobo). Open with $dispatch('open-drawer', 'record-payment'). --}}
@php($suggestedAmount ??= 0)

<x-drawer name="record-payment" :title="'Record a payment'" :description="'For '.$student->fullName().'. It goes to the oldest unpaid invoice first.'" :show="$errors->payment->isNotEmpty()">
    <form method="POST" action="{{ route('school.payments.store', $student) }}" class="flex flex-col gap-4" id="record-payment-form" x-data="{ method: @js(old('method', 'cash')) }">
        @csrf
        <div class="flex flex-col gap-1.5">
            <label for="payment-amount" class="text-sm font-semibold">Amount</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-muted" aria-hidden="true">₦</span>
                <input id="payment-amount" name="amount" inputmode="decimal" autocomplete="off" required class="field pl-8 font-mono" value="{{ old('amount', $suggestedAmount > 0 ? number_format($suggestedAmount / 100) : '') }}" @error('amount', 'payment') aria-invalid="true" @enderror>
            </div>
            @error('amount', 'payment')<p class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
        </div>
        <fieldset class="m-0 flex flex-col gap-1.5 border-0 p-0">
            <legend class="mb-1.5 text-sm font-semibold">Paid by</legend>
            <div class="grid grid-cols-2 gap-2">
                @foreach ($methods as $value => $label)
                    <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-line px-3.5 py-2.5 text-[15px] has-checked:border-brand-600 has-checked:bg-brand-50">
                        <input type="radio" name="method" value="{{ $value }}" x-model="method" class="accent-brand-600">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </fieldset>
        <x-field label="Date paid" name="paid_at" type="date" :value="now()->toDateString()" bag="payment" max="{{ now()->toDateString() }}" required />
        <div x-show="method !== 'cash'">
            <x-field label="Reference" name="reference" bag="payment" placeholder="Bank transfer reference, POS slip or cheque number" />
        </div>
        <div class="flex flex-col gap-1.5">
            <label for="payment-notes" class="text-sm font-semibold">Notes</label>
            <textarea id="payment-notes" name="notes" rows="2" class="field resize-y" placeholder="Optional">{{ old('notes') }}</textarea>
        </div>
    </form>
    <x-slot:footer>
        <x-button variant="brand" icon="check" form="record-payment-form">Record payment</x-button>
    </x-slot:footer>
</x-drawer>
