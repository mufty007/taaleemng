<?php

use App\Actions\Fees\AllocatePayments;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SchoolRole;
use App\Models\ClassLevel;
use App\Models\FeeItem;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Term;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield', 'short_code' => 'GFA']);
    $this->bursar = memberOf($this->school, SchoolRole::Bursar);
    $this->aisha = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Aisha', 'last_name' => 'Bello']);
    $this->student = $this->aisha->student;

    inSchool($this->school, function () {
        $this->term = Term::current();
        $this->jss1 = ClassLevel::query()->where('name', 'JSS 1')->sole();
        $this->tuition = FeeItem::query()->create(['name' => 'Tuition', 'position' => 1]);
        $this->levy = FeeItem::query()->create(['name' => 'Development levy', 'position' => 2]);
        $this->bus = FeeItem::query()->create(['name' => 'School bus', 'is_optional' => true, 'position' => 3]);
        FeeStructure::query()->create(['term_id' => $this->term->id, 'class_level_id' => $this->jss1->id, 'fee_item_id' => $this->tuition->id, 'amount' => 150_000_00]);
        FeeStructure::query()->create(['term_id' => $this->term->id, 'class_level_id' => $this->jss1->id, 'fee_item_id' => $this->levy->id, 'amount' => 20_000_00]);
        FeeStructure::query()->create(['term_id' => $this->term->id, 'class_level_id' => $this->jss1->id, 'fee_item_id' => $this->bus->id, 'amount' => 30_000_00]);
    });

    $this->issue = fn (array $data = []) => $this->actingAs($this->bursar)->post(route('school.invoices.generate', $this->school), ['term_id' => $this->term->id, ...$data]);
    $this->pay = fn (string $amount, array $data = []) => $this->actingAs($this->bursar)->post(route('school.payments.store', [$this->school, $this->student]), [
        'amount' => $amount, 'method' => 'cash', 'paid_at' => now()->toDateString(), ...$data,
    ]);
    $this->invoice = fn () => inSchool($this->school, fn () => Invoice::query()->where('student_id', $this->student->id)->sole());
});

it('saves the fee schedule grid and copies it to another term', function () {
    $secondTerm = inSchool($this->school, fn () => Term::query()->where('sequence', 2)->sole());

    $this->actingAs($this->bursar)->put(route('school.fees.schedule.update', $this->school), [
        'term_id' => $this->term->id,
        'amounts' => [$this->jss1->id => [$this->tuition->id => '₦165,000', $this->levy->id => '', $this->bus->id => '30000']],
    ])->assertSessionHasNoErrors();

    inSchool($this->school, fn () => expect(FeeStructure::query()->where('term_id', $this->term->id)->orderBy('fee_item_id')->pluck('amount')->all())->toBe([165_000_00, 30_000_00]));

    $this->actingAs($this->bursar)->post(route('school.fees.schedule.copy', $this->school), ['from_term_id' => $this->term->id, 'term_id' => $secondTerm->id]);
    inSchool($this->school, fn () => expect(FeeStructure::query()->where('term_id', $secondTerm->id)->count())->toBe(2));

    $this->actingAs($this->bursar)->get(route('school.fees.setup', [$this->school, 'term' => $this->term->id]))->assertOk()->assertSee('165,000');
});

it('issues one invoice per student from the compulsory items, and never twice', function () {
    enrol($this->school, 'SSS 3', 'A');

    ($this->issue)()->assertSessionHas('status', '1 invoice issued for First Term. 1 students were skipped because their class has no fees set for the term.');
    ($this->issue)()->assertSessionHas('status', '0 invoices issued for First Term. 1 students already had one. 1 students were skipped because their class has no fees set for the term.');

    $invoice = ($this->invoice)();
    expect($invoice->total)->toBe(170_000_00)
        ->and($invoice->status)->toBe(InvoiceStatus::Unpaid)
        ->and($invoice->invoice_no)->toBe('INV/'.now()->format('y').'/00001')
        ->and(inSchool($this->school, fn () => $invoice->lines()->pluck('description')->all()))->toBe(['Tuition', 'Development levy']);
});

it('records part payments, then marks the invoice paid', function () {
    ($this->issue)();

    ($this->pay)('100,000')->assertSessionHas('status', '₦100,000 recorded for Aisha Bello. Receipt RCT/'.now()->format('y').'/00001.');
    expect(($this->invoice)())->status->toBe(InvoiceStatus::PartPaid)->amount_paid->toBe(100_000_00);

    ($this->pay)('70000', ['method' => 'transfer', 'reference' => 'GTB-00419'])->assertSessionHasNoErrors();
    expect(($this->invoice)())->status->toBe(InvoiceStatus::Paid)->balance()->toBe(0);
});

it('asks for a reference on transfers, and refuses future dates and nonsense amounts', function () {
    ($this->issue)();

    ($this->pay)('5000', ['method' => 'transfer'])->assertSessionHasErrorsIn('payment', 'reference');
    ($this->pay)('5000', ['paid_at' => now()->addDay()->toDateString()])->assertSessionHasErrorsIn('payment', 'paid_at');
    ($this->pay)('five thousand')->assertSessionHasErrorsIn('payment', 'amount');
    ($this->pay)('0')->assertSessionHasErrorsIn('payment', 'amount');

    inSchool($this->school, fn () => expect(Payment::query()->count())->toBe(0));
});

it('keeps an overpayment as credit and uses it on the next invoice', function () {
    ($this->issue)();
    ($this->pay)('200,000');

    expect(($this->invoice)()->status)->toBe(InvoiceStatus::Paid);
    $this->actingAs($this->bursar)->get(route('school.students.show', [$this->school, $this->student]))->assertSee('In credit')->assertSee('₦30,000');

    $secondTerm = inSchool($this->school, function () {
        $term = Term::query()->where('sequence', 2)->sole();
        FeeStructure::query()->create(['term_id' => $term->id, 'class_level_id' => $this->jss1->id, 'fee_item_id' => $this->tuition->id, 'amount' => 150_000_00]);

        return $term;
    });
    ($this->issue)(['term_id' => $secondTerm->id]);

    inSchool($this->school, fn () => expect(Invoice::query()->where('term_id', $secondTerm->id)->sole())
        ->amount_paid->toBe(30_000_00)
        ->status->toBe(InvoiceStatus::PartPaid));
});

it('adds a discount, and turns anything overpaid into credit', function () {
    ($this->issue)();
    ($this->pay)('170,000');
    $invoice = ($this->invoice)();

    $this->actingAs($this->bursar)->post(route('school.invoices.lines.store', [$this->school, $invoice]), ['kind' => 'discount', 'description' => 'Sibling discount', 'amount' => '17,000'])
        ->assertSessionHas('status', 'Discount added. The invoice total is now ₦153,000.');

    expect(($this->invoice)())->total->toBe(153_000_00)->amount_paid->toBe(153_000_00)->status->toBe(InvoiceStatus::Paid);
    inSchool($this->school, fn () => expect(AllocatePayments::balance($this->student))->toBe(-17_000_00));
});

it('cancels an invoice and moves its payments to credit', function () {
    ($this->issue)();
    ($this->pay)('50,000');

    $this->actingAs($this->bursar)->post(route('school.invoices.void', [$this->school, ($this->invoice)()]), ['reason' => 'Issued twice'])->assertSessionHasNoErrors();

    expect(($this->invoice)())->status->toBe(InvoiceStatus::Void)->balance()->toBe(0)->notes->toContain('Cancelled: Issued twice');
    inSchool($this->school, fn () => expect(AllocatePayments::balance($this->student))->toBe(-50_000_00));
});

it('reverses a payment and reopens the invoice, keeping the receipt on record', function () {
    ($this->issue)();
    ($this->pay)('170,000');
    $payment = inSchool($this->school, fn () => Payment::query()->sole());

    $this->actingAs($this->bursar)->post(route('school.payments.reverse', [$this->school, $payment]), ['reason' => 'Cheque bounced'])->assertSessionHasNoErrors();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Reversed)
        ->and(($this->invoice)()->status)->toBe(InvoiceStatus::Unpaid);
    $this->actingAs($this->bursar)->get(route('school.payments.receipt', [$this->school, $payment]))->assertOk()->assertSee('Reversed');
});

it('prints a receipt in words for staff and the family only', function () {
    ($this->issue)();
    ($this->pay)('170,000');
    $payment = inSchool($this->school, fn () => Payment::query()->sole());
    $receipt = route('school.payments.receipt', [$this->school, $payment]);

    $this->actingAs($this->bursar)->get($receipt)->assertOk()->assertSee('One hundred and seventy thousand naira only');
    $this->actingAs($this->aisha->guardian->user)->get($receipt)->assertOk();
    $this->actingAs($this->student->user)->get($receipt)->assertOk();
    $this->actingAs(enrol($this->school, 'JSS 1', 'A')->guardian->user)->get($receipt)->assertForbidden();
    $this->actingAs(staffOf($this->school)->user)->get($receipt)->assertForbidden();
});

it('lists debtors largest first, and exports them', function () {
    $chidi = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Chidi', 'last_name' => 'Okafor']);
    ($this->issue)();
    ($this->pay)('120,000');

    $this->actingAs($this->bursar)->get(route('school.debtors.index', $this->school))
        ->assertOk()
        ->assertSeeInOrder(['OKAFOR Chidi', '₦170,000', 'BELLO Aisha', '₦50,000']);

    $csv = $this->actingAs($this->bursar)->get(route('school.debtors.index', [$this->school, 'export' => 'csv']))->streamedContent();
    expect($csv)->toContain('Chidi Okafor')->toContain('170000')->toContain($chidi->guardian->phone);
});

it('keeps finance away from teachers and parents', function () {
    $teacher = staffOf($this->school)->user;

    $this->actingAs($teacher)->get(route('school.invoices.index', $this->school))->assertForbidden();
    $this->actingAs($teacher)->post(route('school.payments.store', [$this->school, $this->student]), ['amount' => '1000', 'method' => 'cash', 'paid_at' => now()->toDateString()])->assertForbidden();
    $this->actingAs($this->aisha->guardian->user)->get(route('school.debtors.index', $this->school))->assertForbidden();
    $this->actingAs(memberOf($this->school, SchoolRole::Principal))->get(route('school.invoices.index', $this->school))->assertOk();
    $this->actingAs(memberOf($this->school, SchoolRole::Principal))->post(route('school.invoices.generate', $this->school), ['term_id' => $this->term->id])->assertForbidden();
});

it('shows a parent the balance in the portal', function () {
    ($this->issue)();
    ($this->pay)('100,000');

    $this->actingAs($this->aisha->guardian->user)->get(route('school.portal', $this->school))->assertSee('Fees owed')->assertSee('₦70,000');
    $this->actingAs($this->aisha->guardian->user)->get(route('school.portal.child', [$this->school, $this->student]))
        ->assertOk()
        ->assertSee('Fees outstanding')
        ->assertSee('Pay at the school bursary');
});
