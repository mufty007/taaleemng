<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SchoolRole;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Term;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield']);
    $this->bursar = memberOf($this->school, SchoolRole::Bursar);
    $this->term = inSchool($this->school, fn () => Term::current());
    $this->travelTo($this->term->starts_on->copy()->addWeeks(3)->setTime(12, 0));

    $this->record = fn (array $data = []) => $this->actingAs($this->bursar)->post(route('school.expenses.store', $this->school), [
        'category' => 'power', 'description' => '200 litres of diesel', 'amount' => '190,000', 'spent_on' => today()->toDateString(), 'method' => 'cash', ...$data,
    ]);
});

it('records an expense with its receipt, in the right term', function () {
    Storage::fake('local');

    ($this->record)(['receipt' => UploadedFile::fake()->create('receipt.pdf', 40, 'application/pdf'), 'paid_to' => 'Total, Wuse'])->assertSessionHasNoErrors();

    $expense = inSchool($this->school, fn () => Expense::query()->sole());
    expect($expense->amount)->toBe(190_000_00)
        ->and($expense->term_id)->toBe($this->term->id)
        ->and($expense->receipt['name'])->toBe('receipt.pdf');

    $this->actingAs($this->bursar)->get(route('school.expenses.index', $this->school))->assertOk()->assertSee('200 litres of diesel')->assertSee('₦190,000');
    $this->actingAs($this->bursar)->get(route('school.expenses.receipt', [$this->school, $expense]))->assertOk();
    $this->actingAs(staffOf($this->school)->user)->get(route('school.expenses.receipt', [$this->school, $expense]))->assertForbidden();
});

it('refuses bad amounts, future dates and people who may not record spending', function () {
    ($this->record)(['amount' => 'a lot'])->assertSessionHasErrorsIn('expense', 'amount');
    ($this->record)(['spent_on' => today()->addDay()->toDateString()])->assertSessionHasErrorsIn('expense', 'spent_on');
    ($this->record)(['method' => PaymentMethod::Paystack->value])->assertSessionHasErrorsIn('expense', 'method');

    $principal = memberOf($this->school, SchoolRole::Principal);
    $this->actingAs($principal)->get(route('school.expenses.index', $this->school))->assertOk()->assertDontSee('Record an expense');
    $this->actingAs($principal)->post(route('school.expenses.store', $this->school), ['category' => 'power'])->assertForbidden();
});

it('voids a mistake with a reason, keeping it on record but out of the totals', function () {
    ($this->record)();
    ($this->record)(['description' => 'Generator service', 'category' => 'maintenance', 'amount' => '35,000']);
    $diesel = inSchool($this->school, fn () => Expense::query()->where('description', '200 litres of diesel')->sole());

    $this->actingAs($this->bursar)->post(route('school.expenses.void', [$this->school, $diesel]), [])->assertSessionHasErrorsIn('voidExpense', 'reason');
    $this->actingAs($this->bursar)->post(route('school.expenses.void', [$this->school, $diesel]), ['reason' => 'Entered twice'])->assertSessionHasNoErrors();

    expect($diesel->fresh()->isVoid())->toBeTrue();
    $this->actingAs($this->bursar)->get(route('school.expenses.index', $this->school))->assertSee('Voided: Entered twice')->assertSee('₦35,000 spent');
    $this->assertDatabaseHas('activity_log', ['school_id' => $this->school->id, 'description' => 'voided the expense ₦190,000 (200 litres of diesel): Entered twice']);
});

it('shows fees in against spending out for the term, including holiday payments', function () {
    $student = enrol($this->school)->student;
    inSchool($this->school, function () use ($student) {
        foreach ([[500_000_00, $this->term->starts_on->copy()->subDays(5)], [250_000_00, today()]] as [$amount, $paidAt]) {
            Payment::query()->create(['student_id' => $student->id, 'amount' => $amount, 'method' => 'transfer', 'status' => PaymentStatus::Confirmed, 'paid_at' => $paidAt, 'receipt_no' => 'RCT/'.$amount]);
        }
    });
    ($this->record)(['amount' => '300,000']);

    $this->actingAs($this->bursar)->get(route('school.cashflow.index', $this->school))
        ->assertOk()->assertSee('₦750,000')->assertSee('₦300,000')->assertSee('₦450,000')->assertSee('40% of fees spent');

    $this->actingAs($this->bursar)->get(route('school.cashflow.print', $this->school))
        ->assertOk()->assertSee('Cashflow statement')->assertSee('Powered by');
    $this->actingAs(staffOf($this->school)->user)->get(route('school.cashflow.index', $this->school))->assertForbidden();
});
