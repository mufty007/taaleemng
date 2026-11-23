<?php

use App\Actions\Fees\GenerateInvoices;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\ClassLevel;
use App\Models\FeeItem;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\SchoolSetting;
use App\Models\Term;
use App\Support\Payments\Paystack;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.paystack.secret_key' => 'sk_test_secret', 'services.paystack.base_url' => 'https://api.paystack.test']);

    $this->school = schoolWithStructure(['slug' => 'greenfield']);
    $this->enrolled = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Aisha', 'last_name' => 'Bello'], ['email' => 'amina@family.test']);
    $this->parent = $this->enrolled->guardian->user;

    $this->invoice = inSchool($this->school, function () {
        SchoolSetting::store('paystack_subaccount', 'ACCT_school123');
        $term = Term::current();
        $item = FeeItem::query()->create(['name' => 'Tuition']);
        FeeStructure::query()->create(['term_id' => $term->id, 'class_level_id' => ClassLevel::query()->where('name', 'JSS 1')->value('id'), 'fee_item_id' => $item->id, 'amount' => 150_000_00]);
        app(GenerateInvoices::class)->handle($term);

        return Invoice::query()->sole();
    });

    $this->transaction = fn (string $reference, array $overrides = []) => [
        'status' => 'success', 'reference' => $reference, 'amount' => 150_000_00, 'currency' => 'NGN',
        'paid_at' => '2026-11-21T10:15:00.000Z', 'channel' => 'card', 'gateway_response' => 'Approved', ...$overrides,
    ];
    // What Paystack's verify API reports; a test changes it to simulate a failed or pending payment.
    $this->verifyOverrides = [];
    $this->fakePaystack = fn () => Http::fake([
        'api.paystack.test/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/abc123', 'access_code' => 'abc123', 'reference' => 'x']]),
        'api.paystack.test/transaction/verify/*' => fn (HttpRequest $request) => Http::response(['status' => true, 'data' => ($this->transaction)(basename($request->url()), $this->verifyOverrides)]),
    ]);
    $this->startPayment = function () {
        ($this->fakePaystack)();
        $this->actingAs($this->parent)->post(route('school.portal.invoices.pay', [$this->school, $this->invoice]))->assertRedirect('https://checkout.paystack.com/abc123');

        return inSchool($this->school, fn () => Payment::query()->sole());
    };
    $this->webhook = fn (string $reference, ?string $signature = null) => $this->call('POST', route('webhooks.paystack'), [], [], [], [
        'HTTP_X_PAYSTACK_SIGNATURE' => $signature ?? hash_hmac('sha512', $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $reference]]), 'sk_test_secret'),
        'CONTENT_TYPE' => 'application/json',
    ], $body ?? json_encode(['event' => 'charge.success', 'data' => ['reference' => $reference]]));
});

it('sends the parent to Paystack with a pending payment for the balance', function () {
    $payment = ($this->startPayment)();

    expect($payment)
        ->status->toBe(PaymentStatus::Pending)
        ->method->toBe(PaymentMethod::Paystack)
        ->amount->toBe(150_000_00)
        ->receipt_no->toBeNull()
        ->and($payment->reference)->toStartWith('TAL'.$this->school->id.'-');

    Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api.paystack.test/transaction/initialize'
        && $request['amount'] === 150_000_00
        && $request['email'] === 'amina@family.test'
        && $request['subaccount'] === 'ACCT_school123'
        && $request['callback_url'] === route('school.portal.payments.callback', $this->school)
        && $request->hasHeader('Authorization', 'Bearer sk_test_secret'));
});

it('blocks online pay until the school has a payout subaccount', function () {
    inSchool($this->school, fn () => SchoolSetting::store('paystack_subaccount', null));

    $this->actingAs($this->parent)->post(route('school.portal.invoices.pay', [$this->school, $this->invoice]))
        ->assertRedirect()
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'payouts'));
});

it('can skip the payout subaccount for local testing, but never in production', function () {
    inSchool($this->school, fn () => SchoolSetting::store('paystack_subaccount', null));
    config(['services.paystack.require_subaccount' => false]);
    ($this->fakePaystack)();

    $this->actingAs($this->parent)->post(route('school.portal.invoices.pay', [$this->school, $this->invoice]))->assertRedirect('https://checkout.paystack.com/abc123');
    Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api.paystack.test/transaction/initialize' && ! isset($request['subaccount']));

    expect(app(Paystack::class)->requiresSubaccount())->toBeFalse();
    app()->detectEnvironment(fn () => 'production');
    expect(app(Paystack::class)->requiresSubaccount())->toBeTrue();
    app()->detectEnvironment(fn () => 'testing');
});

it('confirms the payment when the parent comes back, after checking with Paystack', function () {
    $payment = ($this->startPayment)();

    $this->actingAs($this->parent)->get(route('school.portal.payments.callback', [$this->school, 'reference' => $payment->reference]))
        ->assertRedirect(route('school.portal.child', [$this->school, $this->enrolled->student]).'#fees')
        ->assertSessionHas('status', fn (string $status) => str_starts_with($status, 'Thank you. ₦150,000 received, receipt RCT/'));

    expect($payment->fresh())->status->toBe(PaymentStatus::Confirmed)->receipt_no->not->toBeNull()
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('confirms from the webhook only once, however many times Paystack sends it', function () {
    $payment = ($this->startPayment)();

    foreach (range(1, 5) as $attempt) {
        ($this->webhook)($payment->reference)->assertNoContent();
    }
    $this->actingAs($this->parent)->get(route('school.portal.payments.callback', [$this->school, 'reference' => $payment->reference]));

    inSchool($this->school, function () use ($payment) {
        expect(Payment::query()->where('status', PaymentStatus::Confirmed)->count())->toBe(1)
            ->and((int) PaymentAllocation::query()->sum('amount'))->toBe(150_000_00)
            ->and($this->invoice->fresh()->amount_paid)->toBe(150_000_00)
            ->and($payment->fresh()->receipt_no)->toEndWith('00001');
    });
});

it('ignores webhooks without a valid signature', function () {
    $payment = ($this->startPayment)();

    ($this->webhook)($payment->reference, 'forged')->assertUnauthorized();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('fails a payment whose amount does not match', function () {
    $payment = ($this->startPayment)();
    $this->verifyOverrides = ['amount' => 1_000_00];

    ($this->webhook)($payment->reference)->assertNoContent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);
});

it('leaves a payment pending while Paystack is still processing it', function () {
    $payment = ($this->startPayment)();
    $this->verifyOverrides = ['status' => 'ongoing'];

    $this->actingAs($this->parent)->get(route('school.portal.payments.callback', [$this->school, 'reference' => $payment->reference]))
        ->assertSessionHas('status', 'We are waiting for Paystack to confirm your payment. It will show here within a few minutes.');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('keeps an opened but unpaid checkout pending, so the parent can still finish paying', function () {
    $payment = ($this->startPayment)();
    $this->verifyOverrides = ['status' => 'abandoned'];

    ($this->webhook)($payment->reference)->assertNoContent();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);

    $this->verifyOverrides = [];
    ($this->webhook)($payment->reference)->assertNoContent();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('confirms a payment that succeeds with another card after a failed attempt', function () {
    $payment = ($this->startPayment)();
    $this->verifyOverrides = ['status' => 'failed', 'gateway_response' => 'Declined'];

    ($this->webhook)($payment->reference)->assertNoContent();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed);

    $this->verifyOverrides = [];
    ($this->webhook)($payment->reference)->assertNoContent();
    expect($payment->fresh())
        ->status->toBe(PaymentStatus::Confirmed)
        ->receipt_no->not->toBeNull();
});

it('only lets the child’s own parent pay, and only when Paystack is set up', function () {
    ($this->fakePaystack)();
    $stranger = enrol($this->school, 'JSS 1', 'A')->guardian->user;

    $this->actingAs($stranger)->post(route('school.portal.invoices.pay', [$this->school, $this->invoice]))->assertForbidden();
    $this->actingAs($this->parent)->get(route('school.portal.child', [$this->school, $this->enrolled->student]))->assertSee('Pay ₦150,000 online');

    config(['services.paystack.secret_key' => null]);
    $this->actingAs($this->parent)->post(route('school.portal.invoices.pay', [$this->school, $this->invoice]))->assertNotFound();
    $this->actingAs($this->parent)->get(route('school.portal.child', [$this->school, $this->enrolled->student]))->assertDontSee('Pay ₦150,000 online');
});
