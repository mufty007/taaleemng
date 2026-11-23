<?php

use App\Enums\Plan;
use App\Enums\SchoolInvoiceStatus;
use App\Enums\SchoolRole;
use App\Models\PlatformBroadcast;
use App\Models\SchoolInvoice;
use App\Models\User;
use App\Notifications\PlatformBroadcastNotification;
use App\Notifications\SchoolInvoiceNotification;
use App\Support\Finance\FeeStatement;
use App\Support\Platform\Modules;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config([
        'taaleem.plans.standard.per_student' => '1500',
        'taaleem.marketing.prices.standard' => '1500',
    ]);
    $this->admin = User::factory()->platformAdmin()->create();
    $this->school = schoolWithStructure(['slug' => 'greenfield', 'name' => 'Greenfield Academy', 'email' => 'office@greenfield.test']);
    $this->schoolAdmin = memberOf($this->school, SchoolRole::SchoolAdmin, ['email' => 'owner@greenfield.test']);
    $this->teacher = staffOf($this->school)->user;
    enrol($this->school, 'JSS 1', 'A');
    enrol($this->school, 'JSS 1', 'A');
});

it('sets a school’s plan, billing model and seat overrides', function () {
    $this->actingAs($this->admin)->put(route('platform.schools.plan', $this->school), [
        'plan' => 'basic',
        'billing_model' => 'per_student',
        'price_per_student' => '1,200',
        'billing_cycle' => 'termly',
        'max_students' => 180,
        'apply_modules' => '1',
    ])->assertRedirect();

    $school = $this->school->fresh();
    expect($school->plan)->toBe(Plan::Basic)
        ->and($school->billing_model->value)->toBe('per_student')
        ->and($school->pricePerStudent())->toBe(1_200_00)
        ->and($school->max_students)->toBe(180)
        ->and($school->hasModule('payroll'))->toBeTrue()
        ->and($school->hasModule('classwork'))->toBeTrue();
});

it('bills active students × price, then sends, records payment, voids and prints', function () {
    Notification::fake();

    $this->actingAs($this->admin)->put(route('platform.schools.plan', $this->school), [
        'plan' => 'standard',
        'billing_model' => 'per_student',
        'billing_cycle' => 'termly',
    ])->assertRedirect();

    $this->actingAs($this->admin)->post(route('platform.schools.invoices.store', $this->school), ['period' => 'First Term 2026/2027', 'due_on' => now()->addDays(14)->toDateString()])->assertRedirect();

    $invoice = SchoolInvoice::query()->sole();
    expect($invoice->students)->toBe(2)
        ->and($invoice->unit_price)->toBe(1_500_00)
        ->and($invoice->amount)->toBe(3_000_00)
        ->and($invoice->number)->toBe('TAL-'.now()->year.'-0001')
        ->and($invoice->status)->toBe(SchoolInvoiceStatus::Draft);

    $this->actingAs($this->admin)->post(route('platform.school-invoices.send', $invoice))->assertRedirect();
    Notification::assertSentTo($this->schoolAdmin, SchoolInvoiceNotification::class);
    Notification::assertSentOnDemand(SchoolInvoiceNotification::class);
    expect($invoice->fresh()->status)->toBe(SchoolInvoiceStatus::Sent);

    $this->actingAs($this->admin)->get(route('platform.school-invoices.print', $invoice))->assertOk()->assertSee($invoice->number)->assertSee('₦3,000');
    $this->actingAs($this->admin)->get(route('platform.billing.index'))->assertOk()->assertSee('Greenfield Academy')->assertSee('₦3,000');

    $this->actingAs($this->admin)->post(route('platform.school-invoices.paid', $invoice), ['payment_method' => 'Bank transfer', 'payment_reference' => 'GTB-123', 'paid_on' => today()->toDateString()])->assertRedirect();
    expect($invoice->fresh()->status)->toBe(SchoolInvoiceStatus::Paid);
    $this->actingAs($this->admin)->post(route('platform.school-invoices.void', $invoice))->assertStatus(422);
});

it('warns the school admin when the Ta’aleem invoice is overdue, and shows it in Settings', function () {
    SchoolInvoice::query()->create(['school_id' => $this->school->id, 'number' => 'TAL-2026-0009', 'period' => 'First Term', 'students' => 2, 'unit_price' => 1_500_00, 'amount' => 3_000_00, 'status' => SchoolInvoiceStatus::Sent, 'issued_on' => now()->subDays(30), 'due_on' => now()->subDays(3)]);

    $this->actingAs($this->schoolAdmin)->get(route('school.dashboard', $this->school))->assertSee('Your Ta’aleem invoice TAL-2026-0009 is overdue');
    $this->actingAs($this->schoolAdmin)->get(route('school.settings.edit', $this->school))->assertSee('Ta’aleem subscription')->assertSee('Overdue');
    $this->actingAs($this->teacher)->get(route('school.dashboard', $this->school))->assertDontSee('TAL-2026-0009');
});

it('switches modules off: gone from the menu, and the pages explain', function () {
    $this->actingAs($this->admin)->put(route('platform.schools.modules', $this->school), ['modules' => ['chat', 'classwork', 'special-exams', 'admissions', 'inventory']])->assertRedirect();

    expect($this->school->fresh()->disabled_modules)->toBe(['payroll', 'online-payments']);

    $this->actingAs($this->schoolAdmin)->get(route('school.dashboard', $this->school))->assertDontSee(route('school.payroll.index', $this->school));
    $this->actingAs($this->schoolAdmin)->get(route('school.payroll.index', $this->school))->assertForbidden()->assertSee('Payroll isn’t switched on');
    $this->actingAs($this->schoolAdmin)->get(route('school.inventory.index', $this->school))->assertOk();
    inSchool($this->school->fresh(), fn () => expect(FeeStatement::canPayOnline())->toBeFalse());
});

it('broadcasts a notice to the chosen people until it ends or they dismiss it', function () {
    Notification::fake();

    $this->actingAs($this->admin)->post(route('platform.broadcasts.store'), [
        'title' => 'Maintenance on Saturday', 'body' => 'Ta’aleem will be offline 1–2am.', 'level' => 'warning', 'audience' => 'admins', 'email_admins' => '1',
    ])->assertRedirect(route('platform.broadcasts.index'));
    Notification::assertSentTo($this->schoolAdmin, PlatformBroadcastNotification::class);

    $broadcast = PlatformBroadcast::query()->sole();
    $this->actingAs($this->schoolAdmin)->get(route('school.dashboard', $this->school))->assertSee('Maintenance on Saturday');
    $this->actingAs($this->teacher)->get(route('school.dashboard', $this->school))->assertDontSee('Maintenance on Saturday');

    $this->actingAs($this->schoolAdmin)->postJson(route('school.broadcasts.dismiss', [$this->school, $broadcast]))->assertNoContent();
    $this->actingAs($this->schoolAdmin)->get(route('school.dashboard', $this->school))->assertDontSee('Maintenance on Saturday');

    $this->actingAs($this->admin)->post(route('platform.broadcasts.store'), ['title' => 'New: staff chat', 'body' => 'Try it.', 'level' => 'info', 'audience' => 'staff', 'school_ids' => [$this->school->id]]);
    $this->actingAs($this->teacher)->get(route('school.dashboard', $this->school))->assertSee('New: staff chat');

    $this->actingAs($this->admin)->post(route('platform.broadcasts.end', PlatformBroadcast::query()->latest('id')->first()))->assertRedirect();
    $this->actingAs($this->teacher)->get(route('school.dashboard', $this->school))->assertDontSee('New: staff chat');
    $this->actingAs($this->admin)->get(route('platform.broadcasts.index'))->assertOk()->assertSee('Ended');
});

it('keeps billing and broadcasts to super admins', function () {
    $this->actingAs($this->schoolAdmin)->get(route('platform.billing.index'))->assertNotFound();
    $this->actingAs($this->schoolAdmin)->put(route('platform.schools.modules', $this->school), ['modules' => array_keys(Modules::ALL)])->assertNotFound();
    $this->actingAs($this->schoolAdmin)->post(route('platform.broadcasts.store'), ['title' => 'x', 'body' => 'y', 'level' => 'info', 'audience' => 'everyone'])->assertNotFound();
});
