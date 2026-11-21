<?php

use App\Enums\SchoolRole;
use App\Models\InventoryItem;
use App\Models\StockMovement;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield']);
    $this->store = memberOf($this->school, SchoolRole::StoreKeeper);
    $this->admin = memberOf($this->school);
});

it('lets the store keeper catalogue an item with opening stock', function () {
    $this->actingAs($this->store)->post(route('school.inventory.store', $this->school), [
        'name' => 'Senior school shirt (white)',
        'sku' => 'UNI-SHIRT-W',
        'category' => 'uniforms',
        'unit' => 'piece',
        'reorder_level' => 10,
        'unit_cost' => '4,500',
        'opening_quantity' => 40,
    ])->assertSessionHasNoErrors();

    $item = inSchool($this->school, fn () => InventoryItem::query()->sole());
    expect($item->quantity_on_hand)->toBe(40)
        ->and($item->unit_cost)->toBe(4_500_00)
        ->and($item->isLowStock())->toBeFalse();

    expect(inSchool($this->school, fn () => StockMovement::query()->count()))->toBe(1);

    $this->actingAs($this->store)
        ->get(route('school.inventory.index', $this->school))
        ->assertOk()
        ->assertSee('Senior school shirt');
});

it('issues stock and refuses overselling', function () {
    $item = inSchool($this->school, fn () => InventoryItem::query()->create([
        'name' => 'Exercise book (60 leaves)',
        'category' => 'stationery',
        'unit' => 'piece',
        'quantity_on_hand' => 20,
        'reorder_level' => 5,
        'unit_cost' => 350_00,
    ]));

    $this->actingAs($this->store)
        ->post(route('school.inventory.move', [$this->school, $item]), [
            'type' => 'issue',
            'quantity' => 8,
            'note' => 'JSS 1A maths',
        ])
        ->assertSessionHasNoErrors();

    expect($item->fresh()->quantity_on_hand)->toBe(12);

    $this->actingAs($this->store)
        ->post(route('school.inventory.move', [$this->school, $item]), [
            'type' => 'issue',
            'quantity' => 50,
        ])
        ->assertSessionHasErrorsIn('move', 'quantity');

    expect($item->fresh()->quantity_on_hand)->toBe(12);
});

it('flags low stock and hides the module from teachers', function () {
    inSchool($this->school, fn () => InventoryItem::query()->create([
        'name' => 'Lab gloves',
        'category' => 'lab',
        'unit' => 'pack',
        'quantity_on_hand' => 2,
        'reorder_level' => 5,
    ]));

    $this->actingAs($this->store)
        ->get(route('school.inventory.index', [$this->school, 'low' => 1]))
        ->assertOk()
        ->assertSee('Lab gloves')
        ->assertSee('1 low stock');

    $this->actingAs(staffOf($this->school)->user)
        ->get(route('school.inventory.index', $this->school))
        ->assertForbidden();
});
