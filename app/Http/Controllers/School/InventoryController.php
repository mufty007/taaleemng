<?php

namespace App\Http\Controllers\School;

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\InventoryCategory;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Student;
use App\Support\Money;
use App\Support\Tenancy\CurrentSchool;
use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * School store: catalogue items, receive stock, issue or sell, and spot low stock.
 */
class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $category = InventoryCategory::tryFrom((string) $request->query('category'));
        $search = trim((string) $request->query('search'));
        $lowOnly = $request->boolean('low');

        $items = InventoryItem::query()
            ->when($category, fn (Builder $query) => $query->where('category', $category))
            ->when($lowOnly, fn (Builder $query) => $query->lowStock())
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('school.inventory.index', [
            'items' => $items,
            'category' => $category,
            'categories' => InventoryCategory::cases(),
            'search' => $search,
            'lowOnly' => $lowOnly,
            'lowCount' => InventoryItem::query()->active()->lowStock()->count(),
            'canManage' => $request->user()->can('inventory.manage'),
        ]);
    }

    public function store(Request $request, CurrentSchool $currentSchool): RedirectResponse
    {
        $data = $request->validateWithBag('item', [
            'name' => ['required', 'string', 'max:120'],
            'sku' => ['nullable', 'string', 'max:40', Rule::unique('inventory_items', 'sku')->where(fn ($query) => $query->where('school_id', $currentSchool->id()))],
            'category' => ['required', Rule::enum(InventoryCategory::class)],
            'unit' => ['required', 'string', 'max:30'],
            'reorder_level' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'unit_cost' => ['nullable', 'string', 'max:20', 'regex:/^[₦\s\d,]*(\.\d{1,2})?$/u'],
            'opening_quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], ['sku.unique' => 'That SKU is already in use.', 'unit_cost.regex' => 'Enter a cost like 4,500.']);

        $item = InventoryItem::query()->create([
            'name' => $data['name'],
            'sku' => $data['sku'] ?: null,
            'category' => $data['category'],
            'unit' => $data['unit'],
            'reorder_level' => (int) ($data['reorder_level'] ?? 0),
            'unit_cost' => filled($data['unit_cost'] ?? null) ? Money::toKobo($data['unit_cost']) : null,
            'quantity_on_hand' => 0,
            'is_active' => true,
        ]);

        $opening = (int) ($data['opening_quantity'] ?? 0);
        if ($opening > 0) {
            app(RecordStockMovement::class)->handle($item, [
                'type' => StockMovementType::Receive->value,
                'quantity' => $opening,
                'unit_cost' => $item->unit_cost,
                'note' => 'Opening stock',
            ], $request->user());
        }

        activity('inventory')->performedOn($item)->log('added store item '.$item->name);

        return back()->with('status', $item->name.' is in the store catalogue.');
    }

    public function move(Request $request, InventoryItem $inventoryItem, RecordStockMovement $record): RedirectResponse
    {
        $data = $request->validateWithBag('move', [
            'type' => ['required', Rule::enum(StockMovementType::class)],
            'quantity' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:255'],
            'unit_cost' => ['nullable', 'string', 'max:20', 'regex:/^[₦\s\d,]*(\.\d{1,2})?$/u'],
            'student_id' => ['nullable', TenantRule::exists('students')],
        ]);

        if ($data['type'] === StockMovementType::Adjust->value && (int) $data['quantity'] === 0) {
            return back()->withErrors(['quantity' => 'Enter a positive or negative adjustment.'], 'move');
        }

        if (in_array($data['type'], [StockMovementType::Receive->value, StockMovementType::Issue->value, StockMovementType::Sale->value], true)
            && (int) $data['quantity'] <= 0) {
            return back()->withErrors(['quantity' => 'Enter how many items moved.'], 'move');
        }

        $record->handle($inventoryItem, [
            ...$data,
            'unit_cost' => filled($data['unit_cost'] ?? null) ? Money::toKobo($data['unit_cost']) : null,
        ], $request->user());

        return back()->with('status', 'Stock updated for '.$inventoryItem->fresh()->name.'.');
    }

    public function show(Request $request, InventoryItem $inventoryItem): View
    {
        $inventoryItem->load(['movements' => fn ($query) => $query->with(['recorder', 'student'])->latest('moved_at')->latest('id')->limit(50)]);

        return view('school.inventory.show', [
            'item' => $inventoryItem,
            'canManage' => $request->user()->can('inventory.manage'),
            'movementTypes' => collect(StockMovementType::cases())->mapWithKeys(fn (StockMovementType $type) => [$type->value => $type->label()])->all(),
            'students' => Student::query()->orderBy('last_name')->orderBy('first_name')->limit(200)->get(['id', 'ulid', 'first_name', 'last_name', 'admission_no']),
        ]);
    }
}
