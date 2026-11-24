<?php

use App\Enums\BillingModel;
use App\Enums\Plan;
use App\Enums\SchoolInvoiceStatus;
use App\Enums\SchoolRole;
use App\Enums\SchoolStatus;
use App\Models\SchoolInvoice;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.paystack.secret_key' => 'sk_test_secret',
        'services.paystack.base_url' => 'https://api.paystack.test',
        'taaleem.plans.basic.max_students' => 2,
        'taaleem.plans.basic.max_staff' => 10,
        'taaleem.plans.basic.flat_termly' => '45000',
        'taaleem.plans.standard.max_students' => 500,
        'taaleem.plans.standard.max_staff' => 40,
        'taaleem.plans.standard.flat_termly' => '95000',
        'taaleem.plans.standard.flat_annual' => '250000',
        'taaleem.plans.standard.per_student' => '600',
    ]);

    $this->school = schoolWithStructure(['slug' => 'checkout', 'plan' => Plan::Basic, 'status' => SchoolStatus::Trial, 'trial_ends_at' => now()->addDays(10)]);
    $this->admin = memberOf($this->school, SchoolRole::SchoolAdmin, ['email' => 'owner@checkout.test']);
    enrol($this->school, 'JSS 1', 'A');

    // What Paystack's verify API reports for the reference it is asked about.
    $this->paidAmount = null;
    Http::fake([
        'api.paystack.test/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/sub123', 'access_code' => 'sub123', 'reference' => 'x']]),
        'api.paystack.test/transaction/verify/*' => fn (HttpRequest $request) => Http::response(['status' => true, 'data' => [
            'status' => 'success', 'reference' => basename($request->url()), 'amount' => $this->paidAmount, 'currency' => 'NGN', 'paid_at' => now()->toIso8601String(),
        ]]),
    ]);

    $this->webhook = function (string $reference) {
        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $reference]]);

        return $this->call('POST', route('webhooks.paystack'), [], [], [], [
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_secret'),
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    };
});

it('lets a school admin choose a plan and pay for it; the plan switches once Paystack confirms', function () {
    $this->actingAs($this->admin)->post(route('school.settings.subscription.checkout', $this->school), [
        'plan' => 'standard', 'billing_model' => 'flat', 'billing_cycle' => 'annual',
    ])->assertRedirect('https://checkout.paystack.com/sub123');

    $invoice = SchoolInvoice::query()->sole();
    expect($invoice->plan)->toBe(Plan::Standard)
        ->and($invoice->billing_cycle)->toBe('annual')
        ->and($invoice->amount)->toBe(250_000_00)
        ->and($invoice->status)->toBe(SchoolInvoiceStatus::Sent)
        ->and($invoice->paystack_reference)->toStartWith('tal-sub-')
        ->and($this->school->fresh()->plan)->toBe(Plan::Basic);

    $this->paidAmount = 250_000_00;
    ($this->webhook)($invoice->paystack_reference)->assertNoContent();

    $school = $this->school->fresh();
    expect($invoice->fresh()->status)->toBe(SchoolInvoiceStatus::Paid)
        ->and($school->plan)->toBe(Plan::Standard)
        ->and($school->billing_cycle)->toBe('annual')
        ->and($school->status)->toBe(SchoolStatus::Active)
        ->and($school->paid_until->toDateString())->toBe(today()->addYear()->toDateString());
});

it('does not switch the plan when Paystack reports a different amount', function () {
    $this->actingAs($this->admin)->post(route('school.settings.subscription.checkout', $this->school), [
        'plan' => 'standard', 'billing_model' => 'flat', 'billing_cycle' => 'termly',
    ]);
    $invoice = SchoolInvoice::query()->sole();

    $this->paidAmount = 100_00;
    ($this->webhook)($invoice->paystack_reference)->assertNoContent();

    expect($invoice->fresh()->status)->toBe(SchoolInvoiceStatus::Sent)
        ->and($this->school->fresh()->plan)->toBe(Plan::Basic);
});

it('reuses the open invoice on a second click, and replaces it when the admin picks something else', function () {
    $checkout = fn (array $choice) => $this->actingAs($this->admin)->post(route('school.settings.subscription.checkout', $this->school), $choice);

    $checkout(['plan' => 'standard', 'billing_model' => 'flat', 'billing_cycle' => 'termly']);
    $checkout(['plan' => 'standard', 'billing_model' => 'flat', 'billing_cycle' => 'termly']);
    expect(SchoolInvoice::query()->count())->toBe(1);

    $checkout(['plan' => 'standard', 'billing_model' => 'per_student', 'billing_cycle' => 'termly']);

    $invoices = SchoolInvoice::query()->orderBy('id')->get();
    expect($invoices->pluck('status')->all())->toBe([SchoolInvoiceStatus::Void, SchoolInvoiceStatus::Sent])
        ->and($invoices->last()->billing_model)->toBe(BillingModel::PerStudent->value)
        ->and($invoices->last()->amount)->toBe(600_00);
});

it('refuses a plan the school has outgrown', function () {
    enrol($this->school, 'JSS 1', 'A');
    $this->school->update(['plan' => Plan::Standard]);
    enrol($this->school, 'JSS 1', 'A');

    $this->actingAs($this->admin)->get(route('school.settings.edit', $this->school))
        ->assertOk()->assertSee('Choose a plan')->assertSee('Too small for your school today');

    $this->actingAs($this->admin)->post(route('school.settings.subscription.checkout', $this->school), [
        'plan' => 'basic', 'billing_model' => 'flat', 'billing_cycle' => 'termly',
    ])->assertSessionHasErrors('plan');

    expect(SchoolInvoice::query()->count())->toBe(0);
    Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), 'initialize'));
});

it('keeps plan checkout to people who run the school', function () {
    $teacher = staffOf($this->school)->user;

    $this->actingAs($teacher)->post(route('school.settings.subscription.checkout', $this->school), [
        'plan' => 'standard', 'billing_model' => 'flat', 'billing_cycle' => 'termly',
    ])->assertForbidden();

    expect(SchoolInvoice::query()->count())->toBe(0);
});

it('ignores Paystack payments that are not Ta’aleem’s, without asking Paystack about them', function () {
    ($this->webhook)('another-shop-123')->assertNoContent();

    Http::assertNothingSent();
});
