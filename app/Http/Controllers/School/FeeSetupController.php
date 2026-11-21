<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\FeeItem;
use App\Models\FeeStructure;
use App\Models\InvoiceLine;
use App\Models\Term;
use App\Support\Money;
use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * What the school charges: fee items, and the fee schedule (how much each class
 * pays for each item in a term).
 */
class FeeSetupController extends Controller
{
    public function index(Request $request): View
    {
        $term = $this->selectedTerm($request);
        $items = FeeItem::query()->orderBy('position')->orderBy('name')->get();
        $levels = ClassLevel::query()->orderBy('position')->get();
        $amounts = $term
            ? FeeStructure::query()->where('term_id', $term->id)->get()->mapWithKeys(fn (FeeStructure $row) => ["{$row->class_level_id}-{$row->fee_item_id}" => $row->amount])
            : collect();

        return view('school.fees.setup', [
            'term' => $term,
            'terms' => Term::options(),
            'items' => $items,
            'levels' => $levels,
            'amounts' => $amounts,
            'scheduleItems' => $items->where('is_active', true)->values(),
            'levelTotals' => $levels->mapWithKeys(fn (ClassLevel $level) => [$level->id => $items->reject(fn (FeeItem $item) => $item->is_optional || ! $item->is_active)->sum(fn (FeeItem $item) => $amounts["{$level->id}-{$item->id}"] ?? 0)]),
            'itemsInUse' => InvoiceLine::query()->whereNotNull('fee_item_id')->distinct()->pluck('fee_item_id')->flip(),
        ]);
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('addItem', [
            'name' => ['required', 'string', 'max:60', TenantRule::unique('fee_items', 'name')],
            'is_optional' => ['boolean'],
        ]);

        FeeItem::query()->create([...$data, 'position' => (int) FeeItem::query()->max('position') + 1]);

        return back()->with('status', "{$data['name']} added. Set its amounts on the fee schedule.");
    }

    public function updateItem(Request $request, FeeItem $feeItem): RedirectResponse
    {
        $data = $request->validateWithBag('editItem', [
            'name' => ['required', 'string', 'max:60', TenantRule::unique('fee_items', 'name')->ignore($feeItem->id)],
            'is_optional' => ['boolean'],
            'is_active' => ['boolean'],
            'term_id' => ['nullable', TenantRule::exists('terms')],
            'amounts' => ['array'],
            'amounts.*' => ['nullable', 'string', 'max:20', 'regex:/^[₦\s\d,]*(\.\d{1,2})?$/u'],
        ], ['amounts.*.regex' => 'Amounts must be numbers, e.g. 185,000.']);

        $feeItem->update([
            'name' => $data['name'],
            'is_optional' => (bool) ($data['is_optional'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? $feeItem->is_active),
        ]);

        if (! empty($data['term_id']) && $request->has('amounts')) {
            $levelIds = ClassLevel::query()->pluck('id')->flip();
            $rows = [];

            foreach ($data['amounts'] ?? [] as $levelId => $typed) {
                if (! $levelIds->has((int) $levelId) || blank($typed)) {
                    continue;
                }

                $amount = Money::toKobo($typed);

                if ($amount > 100_000_000_00) {
                    throw ValidationException::withMessages(["amounts.{$levelId}" => 'That amount is too large.'])->errorBag('editItem');
                }

                $rows[] = ['class_level_id' => (int) $levelId, 'amount' => $amount];
            }

            DB::transaction(function () use ($feeItem, $data, $rows): void {
                FeeStructure::query()->where('term_id', $data['term_id'])->where('fee_item_id', $feeItem->id)->delete();

                foreach ($rows as $row) {
                    FeeStructure::query()->create(['term_id' => $data['term_id'], 'fee_item_id' => $feeItem->id, ...$row]);
                }
            });
        }

        activity('fees')->performedOn($feeItem)->log("updated the fee item {$feeItem->name}");

        return back()->with('status', "{$feeItem->name} saved.");
    }

    /**
     * Pause an item (left off the schedule and new invoices) or turn it back on.
     */
    public function toggleItem(FeeItem $feeItem): RedirectResponse
    {
        $feeItem->update(['is_active' => ! $feeItem->is_active]);
        activity('fees')->performedOn($feeItem)->log(($feeItem->is_active ? 'turned on the fee item ' : 'paused the fee item ').$feeItem->name);

        return back()->with('status', $feeItem->is_active
            ? "{$feeItem->name} is on again. It will be billed on new invoices."
            : "{$feeItem->name} is paused. Existing invoices keep it; new invoices leave it out.");
    }

    public function destroyItem(FeeItem $feeItem): RedirectResponse
    {
        if (InvoiceLine::query()->where('fee_item_id', $feeItem->id)->exists()) {
            return back()->with('status', "{$feeItem->name} is on invoices already, so it can’t be deleted. Clear its amounts instead.");
        }

        $feeItem->delete();

        return back()->with('status', "{$feeItem->name} deleted.");
    }

    /**
     * Start from the usual Nigerian fee items.
     */
    public function defaults(): RedirectResponse
    {
        foreach (FeeItem::NIGERIAN_DEFAULTS as $position => $name) {
            FeeItem::query()->firstOrCreate(['name' => $name], ['position' => $position + 1]);
        }

        return back()->with('status', 'The usual fee items have been added. Fill in the amounts for each class.');
    }

    /**
     * Save the whole grid for one term. A blank cell means the class does not pay that item.
     */
    public function saveSchedule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'term_id' => ['required', TenantRule::exists('terms')],
            'amounts' => ['array'],
            'amounts.*' => ['array'],
            'amounts.*.*' => ['nullable', 'string', 'max:20', 'regex:/^[₦\s\d,]*(\.\d{1,2})?$/u'],
        ], ['amounts.*.*.regex' => 'Amounts must be numbers, e.g. 185,000.']);

        $levelIds = ClassLevel::query()->pluck('id')->flip();
        // Paused items are not on the form, so their amounts are left as they are.
        $itemIds = FeeItem::query()->where('is_active', true)->pluck('id')->flip();
        $rows = [];

        foreach ($data['amounts'] ?? [] as $levelId => $byItem) {
            foreach ($byItem as $itemId => $typed) {
                if (! $levelIds->has((int) $levelId) || ! $itemIds->has((int) $itemId) || blank($typed)) {
                    continue;
                }

                $amount = Money::toKobo($typed);

                if ($amount > 100_000_000_00) {
                    throw ValidationException::withMessages(["amounts.{$levelId}.{$itemId}" => 'That amount is too large.']);
                }

                $rows[] = ['class_level_id' => (int) $levelId, 'fee_item_id' => (int) $itemId, 'amount' => $amount];
            }
        }

        $term = Term::query()->findOrFail($data['term_id']);

        DB::transaction(function () use ($term, $rows, $itemIds): void {
            FeeStructure::query()->where('term_id', $term->id)->whereIn('fee_item_id', $itemIds->keys())->delete();

            foreach ($rows as $row) {
                FeeStructure::query()->create(['term_id' => $term->id, ...$row]);
            }
        });

        activity('fees')->performedOn($term)->log("updated the fee schedule for {$term->name}");

        return back()->with('status', "Fee schedule for {$term->name} saved.");
    }

    public function copySchedule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_term_id' => ['required', 'different:term_id', TenantRule::exists('terms')],
            'term_id' => ['required', TenantRule::exists('terms')],
        ], ['from_term_id.different' => 'Choose a different term to copy from.']);

        $from = FeeStructure::query()->where('term_id', $data['from_term_id'])->get();

        if ($from->isEmpty()) {
            return back()->with('status', 'That term has no fees to copy.');
        }

        DB::transaction(function () use ($from, $data): void {
            FeeStructure::query()->where('term_id', $data['term_id'])->delete();

            foreach ($from as $row) {
                FeeStructure::query()->create(['term_id' => $data['term_id'], 'class_level_id' => $row->class_level_id, 'fee_item_id' => $row->fee_item_id, 'amount' => $row->amount]);
            }
        });

        return to_route('school.fees.setup', ['term' => $data['term_id']])->with('status', 'Fees copied. Change any amounts that are different this term.');
    }

    private function selectedTerm(Request $request): ?Term
    {
        $termId = $request->integer('term');

        return $termId ? Term::query()->with('academicSession')->find($termId) : Term::current();
    }
}
