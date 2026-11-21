<?php

namespace App\Actions\Inventory;

use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Change stock on hand and leave an audit trail.
 */
class RecordStockMovement
{
    /**
     * @param  array{type: string, quantity: int, note?: ?string, unit_cost?: ?int, student_id?: ?int, moved_at?: ?Carbon}  $data
     */
    public function handle(InventoryItem $item, array $data, User $by): StockMovement
    {
        $type = StockMovementType::from($data['type']);
        $quantity = (int) $data['quantity'];

        if ($quantity === 0) {
            throw ValidationException::withMessages(['quantity' => 'Enter how many items moved.'])->errorBag('move');
        }

        if (in_array($type, [StockMovementType::Issue, StockMovementType::Sale], true) && $quantity < 0) {
            $quantity = abs($quantity);
        }

        return DB::transaction(function () use ($item, $type, $quantity, $data, $by): StockMovement {
            $locked = InventoryItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $delta = match ($type) {
                StockMovementType::Receive => abs($quantity),
                StockMovementType::Issue, StockMovementType::Sale => -abs($quantity),
                StockMovementType::Adjust => $quantity,
            };

            $balance = $locked->quantity_on_hand + $delta;

            if ($balance < 0) {
                throw ValidationException::withMessages([
                    'quantity' => "Only {$locked->quantity_on_hand} {$locked->unit} on hand.",
                ])->errorBag('move');
            }

            if ($type === StockMovementType::Sale && empty($data['student_id'])) {
                throw ValidationException::withMessages(['student_id' => 'Choose the student who bought it.'])->errorBag('move');
            }

            $locked->update([
                'quantity_on_hand' => $balance,
                'unit_cost' => $type === StockMovementType::Receive && isset($data['unit_cost'])
                    ? $data['unit_cost']
                    : $locked->unit_cost,
            ]);

            $movement = StockMovement::query()->create([
                'inventory_item_id' => $locked->id,
                'type' => $type,
                'quantity' => $type === StockMovementType::Adjust ? $quantity : abs($quantity),
                'balance_after' => $balance,
                'unit_cost' => $data['unit_cost'] ?? $locked->unit_cost,
                'note' => $data['note'] ?? null,
                'student_id' => $type === StockMovementType::Sale ? $data['student_id'] : null,
                'recorded_by' => $by->id,
                'moved_at' => $data['moved_at'] ?? now(),
            ]);

            activity('inventory')->performedOn($locked)->log(
                $type->label().' '.$movement->quantity.' '.$locked->unit.' of '.$locked->name.' (now '.$balance.')'
            );

            return $movement;
        });
    }
}
