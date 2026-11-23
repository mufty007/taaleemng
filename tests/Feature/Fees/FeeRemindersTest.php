<?php

use App\Actions\Fees\GenerateInvoices;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReminderChannel;
use App\Enums\SchoolRole;
use App\Models\ClassLevel;
use App\Models\FeeItem;
use App\Models\FeeReminder;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SchoolSetting;
use App\Models\Term;
use App\Notifications\FeeReminderNotification;
use App\Support\Finance\FeeStatement;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config(['services.paystack.secret_key' => 'sk_test_secret', 'services.paystack.base_url' => 'https://api.paystack.test']);

    $this->school = schoolWithStructure(['slug' => 'greenfield', 'name' => 'Greenfield Academy']);
    $this->bursar = memberOf($this->school, SchoolRole::Bursar);
    $this->aisha = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Aisha', 'last_name' => 'Bello'], ['first_name' => 'Amina', 'last_name' => 'Bello', 'email' => 'amina@family.test', 'phone' => '0802 123 4567']);
    $this->musa = enrol($this->school, 'JSS 1', 'A', ['first_name' => 'Musa', 'last_name' => 'Garba'], ['first_name' => 'Sani', 'last_name' => 'Garba', 'email' => null, 'phone' => '0803 222 3333']);

    inSchool($this->school, function () {
        SchoolSetting::store('paystack_subaccount', 'ACCT_school123');
        $term = Term::current();
        $item = FeeItem::query()->create(['name' => 'Tuition']);
        FeeStructure::query()->create(['term_id' => $term->id, 'class_level_id' => ClassLevel::query()->where('name', 'JSS 1')->value('id'), 'fee_item_id' => $item->id, 'amount' => 150_000_00]);
        app(GenerateInvoices::class)->handle($term);
    });

    $this->payLink = fn () => inSchool($this->school, fn () => FeeStatement::linkFor($this->aisha->student));

    Http::fake([
        'api.paystack.test/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/abc123', 'access_code' => 'abc123', 'reference' => 'x']]),
        'api.paystack.test/transaction/verify/*' => fn (HttpRequest $request) => Http::response(['status' => true, 'data' => [
            'status' => 'success', 'reference' => basename($request->url()), 'amount' => 150_000_00, 'currency' => 'NGN',
            'paid_at' => '2026-11-21T10:15:00.000Z', 'channel' => 'card', 'gateway_response' => 'Approved',
        ]]),
    ]);
});

it('gives the bursar a remind button with a written WhatsApp message and pay link', function () {
    $this->actingAs($this->bursar)->get(route('school.debtors.index', $this->school))
        ->assertOk()
        ->assertSee('Email reminders')
        ->assertSee('Remind')
        ->assertSee('2348021234567?text=Dear%20Bello%2C%20this%20is%20a%20reminder', false)
        ->assertSee('Aisha Bello')
        ->assertSee('2 not reminded yet');
});

it('emails one parent a reminder with the pay link and records it', function () {
    Notification::fake();

    $this->actingAs($this->bursar)
        ->postJson(route('school.fee-reminders.store', [$this->school, $this->aisha->student]), ['channel' => 'email'])
        ->assertOk()
        ->assertJson(['message' => 'Reminder emailed to Amina Bello.']);

    Notification::assertSentOnDemand(FeeReminderNotification::class, function (FeeReminderNotification $notification, array $channels, AnonymousNotifiable $notifiable) {
        $mail = $notification->toMail($notifiable);

        return $notifiable->routes['mail'] === ['amina@family.test' => 'Amina Bello']
            && $notification->amount === '₦150,000'
            && str_contains($mail->actionUrl, '/pay/greenfield/'.$this->aisha->student->ulid.'?expires=')
            && $mail->actionText === 'Pay ₦150,000 now';
    });

    $reminder = inSchool($this->school, fn () => FeeReminder::query()->sole());
    expect($reminder->channel)->toBe(ReminderChannel::Email)
        ->and($reminder->amount)->toBe(150_000_00)
        ->and($reminder->sent_by)->toBe($this->bursar->id);

    $this->actingAs($this->bursar)->get(route('school.debtors.index', $this->school))->assertSee('Email · ');
});

it('says so when a parent has no email, records WhatsApp reminders sent from the phone, and holds SMS back until it is ready', function () {
    Notification::fake();
    $remind = fn (string $channel) => $this->actingAs($this->bursar)->postJson(route('school.fee-reminders.store', [$this->school, $this->musa->student]), ['channel' => $channel]);

    $remind('email')->assertUnprocessable()->assertJson(['message' => 'There is no email address for Musa Garba’s parent. Send the reminder by WhatsApp instead.']);
    $remind('whatsapp')->assertOk();
    $remind('sms')->assertUnprocessable()->assertJson(['message' => 'SMS reminders are coming soon. Use WhatsApp or email for now.']);

    Notification::assertNothingSent();
    expect(inSchool($this->school, fn () => FeeReminder::query()->sole()->channel))->toBe(ReminderChannel::WhatsApp);
});

it('emails every family with an email address, once a day at most', function () {
    Notification::fake();

    $this->actingAs($this->bursar)->post(route('school.fee-reminders.bulk', $this->school))
        ->assertSessionHas('status', 'Emailed 1 parent. 1 family have no email address: use WhatsApp for them.');
    $this->actingAs($this->bursar)->post(route('school.fee-reminders.bulk', $this->school))
        ->assertSessionHas('status', 'No reminders emailed. 1 family have no email address: use WhatsApp for them. 1 already emailed in the last day.');

    Notification::assertSentOnDemandTimes(FeeReminderNotification::class, 1);
});

it('lets the principal remind parents but not teachers or the head teacher', function () {
    Notification::fake();
    $url = route('school.fee-reminders.store', [$this->school, $this->aisha->student]);

    $this->actingAs(memberOf($this->school, SchoolRole::Principal))->postJson($url, ['channel' => 'whatsapp'])->assertOk();
    $this->actingAs(memberOf($this->school, SchoolRole::HeadTeacher))->postJson($url, ['channel' => 'whatsapp'])->assertForbidden();
    $this->actingAs(staffOf($this->school)->user)->postJson($url, ['channel' => 'whatsapp'])->assertForbidden();
    $this->actingAs(memberOf($this->school, SchoolRole::HeadTeacher))->post(route('school.fee-reminders.bulk', $this->school))->assertForbidden();
});

it('opens the pay link without signing in and sends the payer to Paystack for the whole balance', function () {
    $link = ($this->payLink)();

    $this->get($link)
        ->assertOk()
        ->assertSee('Aisha Bello')
        ->assertSee('₦150,000')
        ->assertSee('Pay ₦150,000 now')
        ->assertSee('amina@family.test');

    $this->post($link, ['email' => 'Payer@Example.test'])->assertRedirect('https://checkout.paystack.com/abc123');

    $payment = inSchool($this->school, fn () => Payment::query()->sole());
    expect($payment->status)->toBe(PaymentStatus::Pending)->and($payment->amount)->toBe(150_000_00);

    Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api.paystack.test/transaction/initialize'
        && $request['email'] === 'payer@example.test'
        && $request['callback_url'] === route('pay.callback', ['school' => 'greenfield']));

    $this->get(route('pay.callback', ['school' => 'greenfield', 'reference' => $payment->reference]))
        ->assertOk()
        ->assertSee('Payment received');

    expect(inSchool($this->school, fn () => Invoice::query()->where('student_id', $this->aisha->student->id)->sole()->status))->toBe(InvoiceStatus::Paid);
    $this->get($link)->assertOk()->assertSee('All paid up');
});

it('refuses a pay link that was changed or has expired', function () {
    $link = ($this->payLink)();

    $this->get(str_replace($this->aisha->student->ulid, $this->musa->student->ulid, $link))->assertForbidden();
    $this->get(inSchool($this->school, fn () => URL::temporarySignedRoute('pay.show', now()->subMinute(), ['school' => 'greenfield', 'student' => $this->aisha->student->ulid])))->assertForbidden();
    $this->get(route('pay.callback', ['school' => 'greenfield', 'reference' => 'TAL999-01jxxxxxxxxxxxxxxxxxxxxxxx']))->assertOk()->assertSee('Waiting for confirmation');
});

it('shows how to pay at school when online payment is not set up', function () {
    inSchool($this->school, fn () => SchoolSetting::store('paystack_subaccount', null));

    $this->get(($this->payLink)())->assertOk()->assertSee('How to pay')->assertDontSee('Pay ₦150,000 now');
});

it('lets a parent pay everything a child owes from the portal', function () {
    $parent = $this->aisha->guardian->user;

    $this->actingAs($parent)->get(route('school.portal', $this->school))->assertOk()->assertSee('Pay ₦150,000 now');
    $this->actingAs($parent)->post(route('school.portal.children.pay', [$this->school, $this->aisha->student]))->assertRedirect('https://checkout.paystack.com/abc123');
    $this->actingAs($parent)->post(route('school.portal.children.pay', [$this->school, $this->musa->student]))->assertForbidden();

    Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api.paystack.test/transaction/initialize'
        && $request['amount'] === 150_000_00
        && $request['callback_url'] === route('school.portal.payments.callback', $this->school));
});
