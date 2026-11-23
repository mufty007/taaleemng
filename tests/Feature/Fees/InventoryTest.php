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

it('gives the store keeper a counter dashboard with low stock and today’s sales', function () {
    $aisha = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Aisha', 'last_name' => 'Bello'])->student;
    [$shirt, $tie] = inSchool($this->school, fn () => [
        InventoryItem::query()->create(['name' => 'School shirt', 'category' => 'uniforms', 'unit' => 'piece', 'quantity_on_hand' => 12, 'reorder_level' => 10, 'unit_cost' => 4_500_00]),
        InventoryItem::query()->create(['name' => 'School tie', 'category' => 'uniforms', 'unit' => 'piece', 'quantity_on_hand' => 0, 'reorder_level' => 5, 'unit_cost' => 1_500_00]),
    ]);

    $this->actingAs($this->store)
        ->from(route('school.dashboard', $this->school))
        ->post(route('school.inventory.move', [$this->school, $shirt]), ['type' => 'sale', 'quantity' => 3, 'student_id' => $aisha->id, 'item' => $shirt->ulid])
        ->assertRedirect(route('school.dashboard', $this->school));

    $this->actingAs($this->store)->get(route('school.dashboard', $this->school))
        ->assertOk()
        ->assertSee('At the counter')
        ->assertSee('Aisha Bello')
        ->assertSee('₦13,500')
        ->assertSee('1 item out of stock')
        ->assertSee('1 item running low')
        ->assertSee('School shirt (9 left)')
        ->assertSee('Out of stock')
        ->assertDontSee('School at a glance');

    expect($tie->fresh()->quantity_on_hand)->toBe(0);
});

it('tells the office when store items run low', function () {
    inSchool($this->school, fn () => InventoryItem::query()->create(['name' => 'Exercise book', 'category' => 'stationery', 'unit' => 'piece', 'quantity_on_hand' => 2, 'reorder_level' => 10]));

    $this->actingAs($this->admin)->get(route('school.dashboard', $this->school))
        ->assertOk()
        ->assertSee('1 store item low on stock')
        ->assertDontSee('At the counter');
});
