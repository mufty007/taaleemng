<?php

use App\Enums\PayrollRunStatus;
use App\Enums\SchoolRole;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\Staff;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield', 'short_code' => 'GFA']);
    $this->bursar = memberOf($this->school, SchoolRole::Bursar);
    $this->travelTo('2026-10-15 12:00:00');

    $this->teacher = staffOf($this->school, attributes: [
        'first_name' => 'Emeka',
        'last_name' => 'Okonkwo',
        'email' => 'emeka@greenfield.test',
        'designation' => 'Mathematics Teacher',
        'basic_salary' => 180_000_00,
        'allowances' => 20_000_00,
        'deductions' => 21_600_00,
        'bank_name' => 'Access Bank',
        'bank_account_name' => 'Emeka Okonkwo',
        'bank_account_number' => '0123456789',
    ]);

    $this->storeKeeper = staffOf($this->school, SchoolRole::StoreKeeper, [
        'first_name' => 'Fatima',
        'last_name' => 'Bello',
        'email' => 'fatima@greenfield.test',
        'designation' => 'Store Keeper',
        'basic_salary' => 120_000_00,
        'allowances' => 0,
        'deductions' => 12_000_00,
    ]);

    staffOf($this->school, attributes: [
        'first_name' => 'Unpaid',
        'last_name' => 'Volunteer',
        'email' => 'volunteer@greenfield.test',
        'designation' => 'Volunteer',
    ]);
});

it('prepares a month’s payroll from staff salaries and prints payslips', function () {
    $this->actingAs($this->bursar)
        ->post(route('school.payroll.store', $this->school), ['year' => 2026, 'month' => 10])
        ->assertRedirect();

    $run = inSchool($this->school, fn () => PayrollRun::query()->with('lines')->sole());

    expect($run->year)->toBe(2026)
        ->and($run->month)->toBe(10)
        ->and($run->headcount)->toBe(2)
        ->and($run->status)->toBe(PayrollRunStatus::Prepared)
        ->and($run->net_total)->toBe(286_400_00)
        ->and($run->lines)->toHaveCount(2);

    $line = $run->lines->firstWhere('staff_id', $this->teacher->id);
    expect($line->net_pay)->toBe(178_400_00)
        ->and($line->bank_account_number)->toBe('0123456789');

    $this->actingAs($this->bursar)
        ->get(route('school.payroll.show', [$this->school, $run]))
        ->assertOk()
        ->assertSee('Emeka Okonkwo')
        ->assertSee('₦178,400');

    $this->actingAs($this->bursar)
        ->get(route('school.payroll.payslip', [$this->school, $run, $line]))
        ->assertOk()
        ->assertSee('Payslip')
        ->assertSee('One hundred and seventy-eight thousand, four hundred naira only');

    $this->actingAs($this->bursar)
        ->get(route('school.payroll.sheet', [$this->school, $run]))
        ->assertOk()
        ->assertSee('Payroll sheet');
});

it('refuses a second run for the same month until the first is voided', function () {
    $this->actingAs($this->bursar)->post(route('school.payroll.store', $this->school), ['year' => 2026, 'month' => 10]);
    $run = inSchool($this->school, fn () => PayrollRun::query()->sole());

    $this->actingAs($this->bursar)
        ->post(route('school.payroll.store', $this->school), ['year' => 2026, 'month' => 10])
        ->assertSessionHasErrors('month');

    $this->actingAs($this->bursar)
        ->post(route('school.payroll.void', [$this->school, $run]), [])
        ->assertSessionHasErrorsIn('voidPayroll', 'reason');

    $this->actingAs($this->bursar)
        ->post(route('school.payroll.void', [$this->school, $run]), ['reason' => 'Salaries were revised'])
        ->assertRedirect(route('school.payroll.index', $this->school));

    expect($run->fresh()->status)->toBe(PayrollRunStatus::Voided);

    $this->actingAs($this->bursar)
        ->post(route('school.payroll.store', $this->school), ['year' => 2026, 'month' => 10])
        ->assertRedirect();

    expect(inSchool($this->school, fn () => PayrollRun::query()->whereNull('voided_at')->count()))->toBe(1);
});

it('marks the run and individual lines as paid', function () {
    $this->actingAs($this->bursar)->post(route('school.payroll.store', $this->school), ['year' => 2026, 'month' => 10]);
    $run = inSchool($this->school, fn () => PayrollRun::query()->with('lines')->sole());
    $line = $run->lines->first();

    $this->actingAs($this->bursar)
        ->post(route('school.payroll.line-paid', [$this->school, $run, $line]))
        ->assertRedirect();

    expect($line->fresh()->isPaid())->toBeTrue()
        ->and($run->fresh()->status)->toBe(PayrollRunStatus::Prepared);

    $this->actingAs($this->bursar)
        ->post(route('school.payroll.paid', [$this->school, $run]))
        ->assertRedirect();

    expect($run->fresh()->status)->toBe(PayrollRunStatus::Paid)
        ->and(inSchool($this->school, fn () => PayrollLine::query()->whereNull('paid_at')->count()))->toBe(0);
});

it('hides payroll from teachers and principals without payroll.manage', function () {
    $teacher = $this->teacher->user;
    $principal = memberOf($this->school, SchoolRole::Principal);

    $this->actingAs($teacher)->get(route('school.payroll.index', $this->school))->assertForbidden();
    $this->actingAs($principal)->get(route('school.payroll.index', $this->school))->assertForbidden();
    $this->actingAs($this->bursar)->get(route('school.payroll.index', $this->school))->assertOk()->assertSee('Prepare payroll');
});

it('lets an admin set salary details when editing staff', function () {
    $admin = memberOf($this->school);
    $staff = $this->teacher;

    $this->actingAs($admin)
        ->put(route('school.staff.update', [$this->school, $staff]), [
            'title' => 'Mr.',
            'first_name' => 'Emeka',
            'last_name' => 'Okonkwo',
            'gender' => 'male',
            'email' => 'emeka@greenfield.test',
            'phone' => '0803 555 0101',
            'designation' => 'Mathematics Teacher',
            'employment_type' => 'full_time',
            'status' => 'active',
            'role' => 'teacher',
            'basic_salary' => '200,000',
            'allowances' => '25,000',
            'deductions' => '24,000',
            'bank_name' => 'GTBank',
            'bank_account_name' => 'Emeka Okonkwo',
            'bank_account_number' => '0011223344',
        ])
        ->assertRedirect(route('school.staff.show', [$this->school, $staff]));

    $staff->refresh();
    expect($staff->basic_salary)->toBe(200_000_00)
        ->and($staff->allowances)->toBe(25_000_00)
        ->and($staff->bank_name)->toBe('GTBank');
});

it('needs at least one staff member with a salary before preparing payroll', function () {
    inSchool($this->school, fn () => Staff::query()->update(['basic_salary' => null, 'allowances' => 0, 'deductions' => 0]));

    $this->actingAs($this->bursar)
        ->post(route('school.payroll.store', $this->school), ['year' => 2026, 'month' => 11])
        ->assertSessionHasErrors('month');
});
