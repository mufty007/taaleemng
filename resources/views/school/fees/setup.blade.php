@php
    use App\Support\Money;
@endphp

<x-layouts.app title="Fee setup">
    <x-page-header kicker="Finance" title="Fee setup" description="What each class pays per term. Invoices are issued from this schedule.">
        <x-slot:actions>
            @if ($terms)
                <form method="GET">
                    <label for="term" class="sr-only">Term</label>
                    <select id="term" name="term" class="field w-64 bg-white py-2" x-data x-on:change="$el.form.requestSubmit()">
                        @foreach ($terms as $id => $label)<option value="{{ $id }}" @selected($term?->id === $id)>{{ $label }}</option>@endforeach
                    </select>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-tabs :tabs="['schedule' => 'Fee schedule', 'items' => ['label' => 'Fee items', 'count' => $items->count()]]" label="Fee setup">
        <x-tab name="schedule">
            @if ($scheduleItems->isEmpty() && $items->isEmpty())
                <x-card>
                    <x-empty-state icon="receipt" title="No fee items yet" body="Start with the usual ones (Tuition, Development levy, PTA levy, Books, Exam fee, Sports), then add your own.">
                        <form method="POST" action="{{ route('school.fees.items.defaults') }}">
                            @csrf
                            <x-button icon="sparkles">Add the usual fee items</x-button>
                        </form>
                    </x-empty-state>
                </x-card>
            @elseif (! $term)
                <x-card><x-empty-state icon="calendar-range" title="No term yet" body="Start a session on Sessions & terms first." /></x-card>
            @else
                <x-card :padding="false">
                    <form method="POST" action="{{ route('school.fees.schedule.update') }}" id="schedule-form">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="term_id" value="{{ $term->id }}">
                        @if ($errors->any())
                            <p class="m-0 border-b border-line bg-danger-50 px-5 py-2.5 text-sm font-medium text-danger" role="alert">{{ $errors->first() }}</p>
                        @endif
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-left text-sm">
                                <thead>
                                    <tr class="text-xs font-semibold text-muted">
                                        <th scope="col" class="sticky left-0 z-10 bg-white px-5 py-3 font-semibold uppercase">Class</th>
                                        @foreach ($scheduleItems as $item)
                                            <th scope="col" class="min-w-32 px-2 py-3 font-semibold">
                                                {{ $item->name }}
                                                @if ($item->is_optional)<span class="block font-normal text-muted">optional</span>@endif
                                            </th>
                                        @endforeach
                                        <th scope="col" class="px-5 py-3 text-right font-semibold uppercase">Per term</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($levels as $level)
                                        <tr class="border-t border-line">
                                            <th scope="row" class="sticky left-0 z-10 bg-white px-5 py-2 text-left font-semibold whitespace-nowrap">{{ $level->name }}</th>
                                            @foreach ($scheduleItems as $item)
                                                @php $key = "{$level->id}-{$item->id}"; @endphp
                                                <td class="px-2 py-1.5">
                                                    <label for="amount-{{ $key }}" class="sr-only">{{ $item->name }} for {{ $level->name }}</label>
                                                    <div class="relative">
                                                        <span class="pointer-events-none absolute inset-y-0 left-2.5 flex items-center text-muted" aria-hidden="true">₦</span>
                                                        <input
                                                            id="amount-{{ $key }}"
                                                            name="amounts[{{ $level->id }}][{{ $item->id }}]"
                                                            value="{{ old("amounts.{$level->id}.{$item->id}", isset($amounts[$key]) ? number_format($amounts[$key] / 100) : '') }}"
                                                            inputmode="decimal"
                                                            autocomplete="off"
                                                            placeholder="—"
                                                            class="w-full rounded-lg border border-line bg-canvas py-1.5 pr-2 pl-6 text-right font-mono text-sm focus:border-brand-600 focus:bg-white focus:ring-2 focus:ring-brand-100 focus:outline-none"
                                                        >
                                                    </div>
                                                </td>
                                            @endforeach
                                            <td class="px-5 py-2 text-right font-semibold whitespace-nowrap">{{ $levelTotals[$level->id] ? Money::format($levelTotals[$level->id]) : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </form>
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-4">
                        <form method="POST" action="{{ route('school.fees.schedule.copy') }}" class="flex flex-wrap items-center gap-2">
                            @csrf
                            <input type="hidden" name="term_id" value="{{ $term->id }}">
                            <label for="from_term_id" class="text-sm text-muted">Copy amounts from</label>
                            <select id="from_term_id" name="from_term_id" class="field w-56 py-2">
                                @foreach ($terms as $id => $label)
                                    @continue($id === $term->id)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-button variant="secondary" size="sm" icon="copy" onclick="return confirm('Replace this term’s amounts with the ones from the chosen term?')">Copy</x-button>
                        </form>
                        <x-button variant="brand" icon="check" form="schedule-form">Save {{ $term->name }} fees</x-button>
                    </div>
                </x-card>
                <p class="m-0 text-sm text-muted">Leave a cell blank if that class doesn’t pay the item. “Per term” adds up the compulsory items; optional items are added to a student’s invoice by hand. Paused items are hidden here and left off new invoices.</p>
            @endif
        </x-tab>

        <x-tab name="items">
            <x-card :padding="false">
                <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3">
                    <h2 class="m-0 text-base font-semibold">Fee items</h2>
                    <x-button size="sm" icon="plus" type="button" x-data x-on:click="$dispatch('open-drawer', 'add-item')">Add an item</x-button>
                </div>
                @if ($items->isEmpty())
                    <x-empty-state icon="receipt" title="No fee items yet" />
                @else
                    <table class="w-full border-collapse text-left text-[15px]">
                        <tbody>
                            @foreach ($items as $item)
                                @php
                                    $itemAmounts = $levels->mapWithKeys(fn ($level) => [$level->id => $amounts["{$level->id}-{$item->id}"] ?? null])->filter();
                                    $editValues = [
                                        'name' => $item->name,
                                        'is_optional' => $item->is_optional,
                                        'is_active' => $item->is_active,
                                        'amounts' => $levels->mapWithKeys(fn ($level) => [$level->id => isset($itemAmounts[$level->id]) ? number_format($itemAmounts[$level->id] / 100) : ''])->all(),
                                    ];
                                @endphp
                                <tr class="border-t border-line first:border-t-0">
                                    <td class="px-5 py-3">
                                        <span class="block font-medium">{{ $item->name }}</span>
                                        <span class="block text-sm text-muted">
                                            @if ($itemAmounts->isEmpty())
                                                No amount set for {{ $term?->name ?? 'this term' }}
                                            @elseif ($itemAmounts->min() === $itemAmounts->max())
                                                {{ Money::format($itemAmounts->min()) }} for {{ $itemAmounts->count() === $levels->count() ? 'every class' : $itemAmounts->count().' classes' }}
                                            @else
                                                {{ Money::format($itemAmounts->min()) }} – {{ Money::format($itemAmounts->max()) }} across {{ $itemAmounts->count() }} classes
                                            @endif
                                        </span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <x-badge :tone="$item->is_optional ? 'neutral' : 'brand'">{{ $item->is_optional ? 'Optional' : 'Compulsory' }}</x-badge>
                                        @unless ($item->is_active)<x-badge tone="gold">Paused</x-badge>@endunless
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex justify-end gap-1">
                                            <form method="POST" action="{{ route('school.fees.items.toggle', $item) }}">
                                                @csrf
                                                <x-button variant="ghost" size="sm" :icon="$item->is_active ? 'pause' : 'play'">{{ $item->is_active ? 'Pause' : 'Turn on' }}</x-button>
                                            </form>
                                            <x-button variant="secondary" size="sm" icon="pencil" type="button" x-data x-on:click="$dispatch('edit-item', { action: {{ \Illuminate\Support\Js::from(route('school.fees.items.update', $item)) }}, values: {{ \Illuminate\Support\Js::from($editValues) }} })">Edit</x-button>
                                            @unless ($itemsInUse->has($item->id))
                                                <form method="POST" action="{{ route('school.fees.items.destroy', $item) }}" onsubmit="return confirm('Delete {{ $item->name }}?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-button variant="ghost" size="sm" icon="trash-2">Delete</x-button>
                                                </form>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>
        </x-tab>
    </x-tabs>

    <x-drawer name="add-item" title="Add a fee item" :show="$errors->addItem->isNotEmpty()">
        <form method="POST" action="{{ route('school.fees.items.store') }}" class="flex flex-col gap-4">
            @csrf
            <x-field label="Name" name="name" bag="addItem" placeholder="e.g. School bus" required />
            <label class="flex items-center gap-3 text-[15px]">
                <input type="hidden" name="is_optional" value="0">
                <input type="checkbox" name="is_optional" value="1" class="size-4 accent-brand-600" @checked(old('is_optional'))>
                Optional (only some students pay it, e.g. bus or boarding)
            </label>
            <x-button variant="brand" class="self-start">Add item</x-button>
        </form>
    </x-drawer>

    @php
        // After a failed save, reopen the panel with what was typed.
        $reopen = $errors->editItem->isNotEmpty() ? $items->firstWhere('id', (int) old('item_id')) : null;
        $initial = $reopen
            ? ['action' => route('school.fees.items.update', $reopen), 'values' => ['name' => old('name'), 'is_optional' => (bool) old('is_optional'), 'is_active' => (bool) old('is_active'), 'amounts' => (object) old('amounts', [])]]
            : ['values' => ['name' => '', 'is_optional' => false, 'is_active' => true, 'amounts' => (object) []]];
    @endphp
    <div x-data="editPanel('item', @js($initial))">
        <x-drawer name="item" title="Edit fee item" title-expression="values.name || 'Edit fee item'" :description="'Name, type and what each class pays for '.($term ? $term->name.' '.$term->academicSession->name : 'this term').'. Invoices already issued keep their amounts.'" :show="$reopen !== null" width="sm:max-w-lg">
            <form method="POST" :action="action" class="flex flex-col gap-5" id="edit-item-form" x-data="{ every: '' }">
                @csrf
                @method('PATCH')
                <input type="hidden" name="term_id" value="{{ $term?->id }}">
                <input type="hidden" name="item_id" :value="action.split('/').pop()">
                <div class="flex flex-col gap-1.5">
                    <label for="edit-item-name" class="text-sm font-semibold">Name</label>
                    <input id="edit-item-name" name="name" class="field" x-model="values.name" required maxlength="60">
                    @error('name', 'editItem')<p class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
                </div>

                <fieldset class="m-0 flex flex-col gap-2 border-0 p-0">
                    <legend class="mb-1.5 text-sm font-semibold">Who pays it</legend>
                    <input type="hidden" name="is_optional" :value="values.is_optional ? 1 : 0">
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" class="rounded-xl border px-3 py-2.5 text-left text-sm transition" :class="! values.is_optional ? 'border-brand-600 bg-brand-50 font-semibold text-brand-700' : 'border-line bg-white text-muted'" x-on:click="values.is_optional = false">Compulsory<span class="block text-xs font-normal">On every invoice</span></button>
                        <button type="button" class="rounded-xl border px-3 py-2.5 text-left text-sm transition" :class="values.is_optional ? 'border-brand-600 bg-brand-50 font-semibold text-brand-700' : 'border-line bg-white text-muted'" x-on:click="values.is_optional = true">Optional<span class="block text-xs font-normal">Added to a student by hand</span></button>
                    </div>
                </fieldset>

                <label class="flex items-start gap-3 rounded-xl border border-line px-3 py-2.5 text-[15px]">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="mt-1 size-4 accent-brand-600" x-model="values.is_active">
                    <span>Active<span class="block text-sm text-muted">Untick to pause it: it stays on old invoices but is left off new ones.</span></span>
                </label>

                @if ($term)
                    <div class="flex flex-col gap-2">
                        <div class="flex items-end justify-between gap-3">
                            <span class="text-sm font-semibold">Amount per class · {{ $term->name }}</span>
                            <div class="flex items-center gap-1.5">
                                <label for="edit-item-every" class="sr-only">Same amount for every class</label>
                                <input id="edit-item-every" x-model="every" inputmode="decimal" placeholder="Same for all" class="field w-32 py-1.5 text-right font-mono text-sm">
                                <button type="button" class="rounded-lg bg-brand-50 px-2.5 py-1.5 text-sm font-semibold text-brand-700 hover:bg-brand-100" x-on:click="Object.keys(values.amounts).forEach((level) => values.amounts[level] = every)">Apply</button>
                            </div>
                        </div>
                        <div class="divide-y divide-line rounded-xl border border-line">
                            @foreach ($levels as $level)
                                <div class="flex items-center justify-between gap-3 px-3 py-1.5">
                                    <label for="edit-item-amount-{{ $level->id }}" class="text-sm">{{ $level->name }}</label>
                                    <div class="relative w-36">
                                        <span class="pointer-events-none absolute inset-y-0 left-2.5 flex items-center text-muted" aria-hidden="true">₦</span>
                                        <input id="edit-item-amount-{{ $level->id }}" name="amounts[{{ $level->id }}]" inputmode="decimal" placeholder="Not charged" class="field py-1.5 pl-7 text-right font-mono text-sm" x-model="values.amounts[{{ $level->id }}]">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('amounts', 'editItem')<p class="m-0 text-[13px] font-medium text-danger">{{ $message }}</p>@enderror
                        @if ($errors->editItem->has('amounts.*'))<p class="m-0 text-[13px] font-medium text-danger">{{ $errors->editItem->first('amounts.*') }}</p>@endif
                        <p class="m-0 text-xs text-muted">Leave a class blank if it doesn’t pay this item.</p>
                    </div>
                @endif
            </form>
            <x-slot:footer><x-button variant="brand" icon="check" form="edit-item-form">Save fee item</x-button></x-slot:footer>
        </x-drawer>
    </div>
</x-layouts.app>
