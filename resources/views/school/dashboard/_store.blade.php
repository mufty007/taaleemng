{{-- Store keeper: the counter on the left (record, running low, movements), the term on the right. --}}
@php
    $moveErrors = $errors->getBag('move');
    $quickType = old('type', 'sale');
@endphp

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
    <div class="flex min-w-0 flex-col gap-6">
        {{-- At the counter: one form for sales, free issues and deliveries. --}}
        <x-card id="counter" :padding="false">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-5 py-4">
                <div>
                    <h2 class="m-0 text-base font-semibold">At the counter</h2>
                    <p class="m-0 text-sm text-muted">Record a sale, a free issue or a delivery without leaving this page.</p>
                </div>
            </div>
            @if ($storeItems->isEmpty())
                <x-empty-state icon="package" title="No store items yet" body="Add uniforms, books and other items to the catalogue first.">
                    <x-button :href="route('school.inventory.index')" icon="plus">Open inventory</x-button>
                </x-empty-state>
            @else
                <form
                    method="POST"
                    x-data="{ item: @js((string) old('item', '')), type: @js($quickType), url: @js(route('school.inventory.move', '__item__')) }"
                    :action="url.replace('__item__', item)"
                    class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2"
                >
                    @csrf
                    <fieldset class="m-0 border-0 p-0 sm:col-span-2">
                        <legend class="mb-1.5 text-sm font-semibold">What happened?</legend>
                        <div class="grid grid-cols-3 gap-1 rounded-xl bg-chip p-1" role="radiogroup">
                            @foreach (['sale' => ['Sale', 'shopping-bag'], 'issue' => ['Free issue', 'hand-helping'], 'receive' => ['Delivery in', 'package-plus']] as $value => [$text, $icon])
                                <label class="relative cursor-pointer">
                                    <input type="radio" name="type" value="{{ $value }}" x-model="type" class="peer sr-only">
                                    <span class="flex items-center justify-center gap-2 rounded-lg px-2 py-2 text-sm font-semibold text-muted transition peer-checked:bg-white peer-checked:text-ink peer-checked:shadow-sm peer-focus-visible:ring-2 peer-focus-visible:ring-brand-600">
                                        <x-dynamic-component :component="'lucide-'.$icon" class="size-4 flex-none" aria-hidden="true" />
                                        <span class="truncate">{{ $text }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="flex flex-col gap-1.5 sm:col-span-2">
                        <label for="counter-item" class="text-sm font-semibold">Item</label>
                        <div class="relative">
                            <select id="counter-item" name="item" x-model="item" required @class(['field appearance-none pr-10', 'field-error' => $moveErrors->has('quantity')])>
                                <option value="">Choose an item</option>
                                @foreach ($storeItems as $storeItem)
                                    <option value="{{ $storeItem->ulid }}">{{ $storeItem->name }} · {{ $storeItem->quantity_on_hand }} {{ $storeItem->unit }} left</option>
                                @endforeach
                            </select>
                            <x-lucide-chevron-down class="pointer-events-none absolute top-1/2 right-3.5 size-[18px] -translate-y-1/2 text-muted" aria-hidden="true" />
                        </div>
                    </div>

                    <x-field label="Quantity" name="quantity" type="number" min="1" bag="move" :value="old('quantity', 1)" required />

                    <div class="flex flex-col gap-1.5" x-show="type === 'sale'">
                        <label for="counter-student" class="text-sm font-semibold">Bought by</label>
                        <div class="relative">
                            <select id="counter-student" name="student_id" :disabled="type !== 'sale'" :required="type === 'sale'" @class(['field appearance-none pr-10', 'field-error' => $moveErrors->has('student_id')])>
                                <option value="">Choose the student</option>
                                @foreach ($buyers as $class => $enrolments)
                                    <optgroup label="{{ $class }}">
                                        @foreach ($enrolments as $enrolment)
                                            <option value="{{ $enrolment->student->id }}" @selected((string) old('student_id') === (string) $enrolment->student->id)>{{ $enrolment->student->fullName() }} · {{ $enrolment->student->admission_no }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <x-lucide-chevron-down class="pointer-events-none absolute top-1/2 right-3.5 size-[18px] -translate-y-1/2 text-muted" aria-hidden="true" />
                        </div>
                        @if ($moveErrors->has('student_id'))
                            <p class="m-0 text-[13px] font-medium text-danger">{{ $moveErrors->first('student_id') }}</p>
                        @endif
                    </div>

                    <div x-show="type !== 'sale'">
                        <x-field label="Note" name="note" bag="move" :value="old('note')" placeholder="e.g. Library, or the supplier’s name" />
                    </div>

                    <div class="flex flex-wrap items-center gap-3 sm:col-span-2">
                        <x-button variant="brand" icon="check" ::disabled="item === ''">
                            <span x-text="{ sale: 'Record sale', issue: 'Record issue', receive: 'Record delivery' }[type]">Record</span>
                        </x-button>
                        <a href="{{ route('school.inventory.index') }}" class="text-sm font-semibold text-brand-600 hover:text-ink">Add a new item</a>
                    </div>
                </form>
            @endif
        </x-card>

        {{-- Running low --}}
        <x-card :padding="false">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <div>
                    <h2 class="m-0 text-base font-semibold">Running low</h2>
                    <p class="m-0 text-sm text-muted">At or below the reorder level you set for each item.</p>
                </div>
                <a href="{{ route('school.inventory.index', ['low' => 1]) }}" class="text-sm font-semibold text-brand-600 hover:text-ink">All low stock</a>
            </div>
            @if ($lowStock->isEmpty())
                <x-empty-state icon="package-check" title="Nothing is running low" body="Every item is above its reorder level." />
            @else
                <ul class="m-0 list-none p-0">
                    @foreach ($lowStock as $item)
                        @php
                            $left = min(100, round($item->quantity_on_hand / max(1, $item->reorder_level * 2) * 100));
                        @endphp
                        <li class="flex items-center gap-4 border-b border-line px-5 py-3 last:border-b-0">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('school.inventory.show', $item) }}" class="truncate font-semibold hover:text-brand-600">{{ $item->name }}</a>
                                    <x-badge :tone="$item->quantity_on_hand === 0 ? 'danger' : 'gold'">{{ $item->quantity_on_hand === 0 ? 'Out of stock' : 'Low' }}</x-badge>
                                </div>
                                <div class="mt-2 flex items-center gap-3">
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-chip" aria-hidden="true">
                                        <div @class(['h-full rounded-full', 'bg-danger' => $item->quantity_on_hand === 0 || $left < 25, 'bg-gold' => $item->quantity_on_hand > 0 && $left >= 25]) style="width: {{ max(2, $left) }}%"></div>
                                    </div>
                                    <span class="flex-none text-xs text-muted">{{ $item->quantity_on_hand }} {{ $item->unit }} left · reorder at {{ $item->reorder_level }}</span>
                                </div>
                            </div>
                            <x-button variant="soft" size="sm" icon="package-plus" :href="route('school.inventory.show', $item)">Restock</x-button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        {{-- Out of the store each day --}}
        <x-card>
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="m-0 text-base font-semibold">Units out, last {{ $outTrend->count() }} school days</h2>
                <span class="text-sm text-muted">Sales and free issues</span>
            </div>
            <x-report.columns
                class="mt-5"
                tone="naija"
                label="Units sold or issued each school day"
                :bars="$outTrend->map(fn (array $day, int $index) => ['label' => $day['date']->format('D j'), 'value' => $day['out'], 'display' => number_format($day['out']).' units', 'current' => $index === $outTrend->count() - 1])->all()"
            />
        </x-card>

        {{-- Latest movements --}}
        <x-card :padding="false">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <h2 class="m-0 text-base font-semibold">Latest movements</h2>
                <a href="{{ route('school.inventory.index') }}" class="text-sm font-semibold text-brand-600 hover:text-ink">Inventory</a>
            </div>
            @if ($recentMovements->isEmpty())
                <x-empty-state icon="history" title="No movements yet" body="Sales, issues and deliveries show here as you record them." />
            @else
                <ul class="m-0 list-none p-0">
                    @foreach ($recentMovements as $movement)
                        @php
                            [$icon, $tone] = match ($movement->type) {
                                \App\Enums\StockMovementType::Sale => ['shopping-bag', 'bg-naija-50 text-naija'],
                                \App\Enums\StockMovementType::Issue => ['hand-helping', 'bg-sky-50 text-sky'],
                                \App\Enums\StockMovementType::Receive => ['package-plus', 'bg-brand-50 text-brand-600'],
                                default => ['sliders-horizontal', 'bg-chip text-muted'],
                            };
                            $out = in_array($movement->type, [\App\Enums\StockMovementType::Sale, \App\Enums\StockMovementType::Issue], true) || $movement->quantity < 0;
                        @endphp
                        <li class="flex items-center gap-3 border-b border-line px-5 py-3 last:border-b-0">
                            <span class="grid size-8 flex-none place-items-center rounded-full {{ $tone }}"><x-dynamic-component :component="'lucide-'.$icon" class="size-4" aria-hidden="true" /></span>
                            <div class="min-w-0 flex-1 text-sm">
                                <span class="block truncate font-semibold">{{ $movement->item?->name ?? 'Removed item' }}</span>
                                <span class="block truncate text-xs text-muted">
                                    {{ $movement->type->label() }}{{ $movement->student ? ' · '.$movement->student->fullName() : '' }}{{ $movement->note ? ' · '.$movement->note : '' }} · {{ $movement->moved_at->diffForHumans() }}
                                </span>
                            </div>
                            <span @class(['font-mono text-sm font-semibold', 'text-danger' => $out, 'text-naija' => ! $out])>{{ $out ? '−' : '+' }}{{ abs($movement->quantity) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    <aside class="flex min-w-0 flex-col gap-6">
        <x-card>
            <span class="text-sm font-medium text-muted">Sales this term</span>
            <div class="mt-1 text-2xl font-bold tracking-[-0.02em]">{{ \App\Support\Money::format($termSales) }}</div>
            <p class="m-0 mt-1 text-[13px] text-muted">At the cost recorded with each sale.</p>
            @can('view-reports')
                <x-button variant="secondary" size="sm" icon="bar-chart-3" :href="route('school.reports.show', 'inventory')" class="mt-4">Inventory report</x-button>
            @endcan
        </x-card>
        @include('school.announcements._latest')
        @include('school.dashboard._term')
    </aside>
</div>
