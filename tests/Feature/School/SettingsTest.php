<?php

use App\Enums\SchoolRole;
use App\Models\NumberSequence;
use App\Models\SchoolSetting;
use App\Support\Results\PromotionRules;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->school = schoolWithStructure(['slug' => 'greenfield', 'short_code' => 'GFA']);
    $this->admin = memberOf($this->school);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function settingsForm(array $overrides = []): array
{
    return [
        'promotion_pass_mark' => 40,
        'class_structure' => test()->school->class_structure->value,
        'numbering' => config('taaleem.numbering'),
        ...$overrides,
    ];
}

it('shows settings to school admins and hides them from teachers', function () {
    $this->actingAs($this->admin)
        ->get(route('school.settings.edit', $this->school))
        ->assertOk()
        ->assertSee('Promotion pass mark')
        ->assertSee('Document numbers')
        ->assertSee('Settings sections')
        ->assertSee('data-tab="subscription"', false)
        ->assertSee('data-tab="onboarding"', false);

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
        ->put(route('school.settings.update', $this->school), settingsForm([
            'admissions_open' => '1',
            'show_positions' => '1',
            'promotion_pass_mark' => '45',
            'numbering' => $patterns,
            '_tab' => 'numbering',
        ]))
        ->assertRedirect(route('school.settings.edit', $this->school).'#numbering')
        ->assertSessionHas('status', 'Settings saved.');

    inSchool($this->school, function () {
        expect(SchoolSetting::valueFor('admissions_open'))->toBeTrue()
            ->and(SchoolSetting::valueFor('show_positions'))->toBeTrue()
            ->and((float) SchoolSetting::valueFor('promotion_pass_mark'))->toBe(45.0)
            ->and(PromotionRules::passMark())->toBe(45.0)
            ->and(NumberSequence::query()->where('key', 'admission_no')->value('pattern'))->toBe('GFA/{YYYY}/{SEQ:5}');
    });
});

it('creates a Paystack payout subaccount from the school bank details', function () {
    config(['services.paystack.secret_key' => 'sk_test_secret', 'services.paystack.base_url' => 'https://api.paystack.test']);
    Http::fake([
        'api.paystack.test/subaccount' => Http::response(['status' => true, 'data' => ['subaccount_code' => 'ACCT_school123']]),
    ]);

    $patterns = collect(config('taaleem.numbering'))->all();

    $this->actingAs($this->admin)
        ->put(route('school.settings.update', $this->school), settingsForm([
            'payout_bank_code' => '058',
            'payout_account_number' => '0123456789',
            'payout_account_name' => 'Greenfield Academy',
            'numbering' => $patterns,
            '_tab' => 'payouts',
        ]))
        ->assertRedirect(route('school.settings.edit', $this->school).'#payouts')
        ->assertSessionHasNoErrors();

    inSchool($this->school, function () {
        expect(SchoolSetting::valueFor('paystack_subaccount'))->toBe('ACCT_school123')
            ->and(SchoolSetting::valueFor('payout_bank_code'))->toBe('058')
            ->and(SchoolSetting::valueFor('payout_account_number'))->toBe('0123456789');
    });
});

it('refuses numbering patterns without a sequence token', function () {
    $patterns = collect(config('taaleem.numbering'))->all();
    $patterns['receipt_no'] = 'RCT/{YY}/FIXED';

    $this->actingAs($this->admin)
        ->put(route('school.settings.update', $this->school), settingsForm([
            'numbering' => $patterns,
        ]))
        ->assertSessionHasErrors('numbering.receipt_no');
});

it('does not let a principal without school.manage change settings', function () {
    $principal = memberOf($this->school, SchoolRole::Principal);

    $this->actingAs($principal)
        ->put(route('school.settings.update', $this->school), settingsForm([
            'promotion_pass_mark' => 50,
        ]))
        ->assertForbidden();
});
