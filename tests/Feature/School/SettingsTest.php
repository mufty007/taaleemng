<?php

use App\Enums\SchoolRole;
use App\Models\NumberSequence;
use App\Models\SchoolSetting;
use App\Support\Results\PromotionRules;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield', 'short_code' => 'GFA']);
    $this->admin = memberOf($this->school);
});

it('shows settings to school admins and hides them from teachers', function () {
    $this->actingAs($this->admin)
        ->get(route('school.settings.edit', $this->school))
        ->assertOk()
        ->assertSee('Promotion pass mark')
        ->assertSee('Document numbers');

    $this->actingAs(staffOf($this->school)->user)
        ->get(route('school.settings.edit', $this->school))
        ->assertForbidden();
});

it('saves admissions, promotion and numbering preferences', function () {
    $patterns = collect(config('taaleem.numbering'))->map(fn (string $pattern, string $key) => match ($key) {
        'admission_no' => 'GFA/{YYYY}/{SEQ:5}',
        default => $pattern,
    })->all();

    $this->actingAs($this->admin)
        ->put(route('school.settings.update', $this->school), [
            'admissions_open' => '1',
            'show_positions' => '1',
            'promotion_pass_mark' => '45',
            'paystack_subaccount' => 'ACCT_test123',
            'numbering' => $patterns,
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Settings saved.');

    inSchool($this->school, function () {
        expect(SchoolSetting::valueFor('admissions_open'))->toBeTrue()
            ->and(SchoolSetting::valueFor('show_positions'))->toBeTrue()
            ->and((float) SchoolSetting::valueFor('promotion_pass_mark'))->toBe(45.0)
            ->and(SchoolSetting::valueFor('paystack_subaccount'))->toBe('ACCT_test123')
            ->and(PromotionRules::passMark())->toBe(45.0)
            ->and(NumberSequence::query()->where('key', 'admission_no')->value('pattern'))->toBe('GFA/{YYYY}/{SEQ:5}');
    });
});

it('refuses numbering patterns without a sequence token', function () {
    $patterns = collect(config('taaleem.numbering'))->all();
    $patterns['receipt_no'] = 'RCT/{YY}/FIXED';

    $this->actingAs($this->admin)
        ->put(route('school.settings.update', $this->school), [
            'promotion_pass_mark' => 40,
            'numbering' => $patterns,
        ])
        ->assertSessionHasErrors('numbering.receipt_no');
});

it('does not let a principal without school.manage change settings', function () {
    $principal = memberOf($this->school, SchoolRole::Principal);

    $this->actingAs($principal)
        ->put(route('school.settings.update', $this->school), [
            'promotion_pass_mark' => 50,
            'numbering' => config('taaleem.numbering'),
        ])
        ->assertForbidden();
});
