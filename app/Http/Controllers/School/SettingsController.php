<?php

namespace App\Http\Controllers\School;

use App\Actions\Fees\SyncSchoolPayoutSubaccount;
use App\Http\Controllers\Controller;
use App\Models\NumberSequence;
use App\Models\SchoolSetting;
use App\Support\Numbering\SequenceGenerator;
use App\Support\Payments\NigerianBanks;
use App\Support\Payments\Paystack;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * School-wide preferences: admissions, promotion, payouts and document numbers.
 */
class SettingsController extends Controller
{
    /**
     * @return array<string, string>
     */
    public static function numberingLabels(): array
    {
        return [
            'admission_no' => 'Admission numbers',
            'staff_no' => 'Staff numbers',
            'application_no' => 'Application numbers',
            'invoice_no' => 'Fee invoices',
            'receipt_no' => 'Payment receipts',
            'payslip_no' => 'Payslips',
        ];
    }

    public function edit(CurrentSchool $currentSchool, SequenceGenerator $sequences, Paystack $paystack): View
    {
        $school = $currentSchool->getOrFail();
        $stored = NumberSequence::query()->get()->keyBy('key');

        $numbering = collect(config('taaleem.numbering'))->map(function (string $default, string $key) use ($stored, $sequences, $school) {
            $pattern = $stored[$key]->pattern ?? $default;
            $next = $stored[$key]->next_value ?? 1;

            return [
                'key' => $key,
                'label' => self::numberingLabels()[$key] ?? $key,
                'pattern' => $pattern,
                'next_value' => $next,
                'preview' => $sequences->format($pattern, $next, $school->short_code),
            ];
        })->values()->all();

        return view('school.settings.edit', [
            'school' => $school,
            'admissionsOpen' => (bool) SchoolSetting::valueFor('admissions_open'),
            'promotionPassMark' => (float) SchoolSetting::valueFor('promotion_pass_mark'),
            'showPositions' => (bool) SchoolSetting::valueFor('show_positions'),
            'payoutBankCode' => SchoolSetting::valueFor('payout_bank_code'),
            'payoutAccountNumber' => SchoolSetting::valueFor('payout_account_number'),
            'payoutAccountName' => SchoolSetting::valueFor('payout_account_name'),
            'paystackReady' => filled(SchoolSetting::valueFor('paystack_subaccount')),
            'platformPaystackReady' => $paystack->isConfigured(),
            'banks' => NigerianBanks::options(),
            'numbering' => $numbering,
        ]);
    }

    public function update(Request $request, CurrentSchool $currentSchool, SyncSchoolPayoutSubaccount $syncPayout): RedirectResponse
    {
        $school = $currentSchool->getOrFail();
        $numberingKeys = array_keys(config('taaleem.numbering'));

        $data = $request->validate([
            'admissions_open' => ['sometimes', 'boolean'],
            'show_positions' => ['sometimes', 'boolean'],
            'promotion_pass_mark' => ['required', 'numeric', 'min:0', 'max:100'],
            'payout_bank_code' => ['nullable', 'string', Rule::in(array_keys(NigerianBanks::options()))],
            'payout_account_number' => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'payout_account_name' => ['nullable', 'string', 'max:120'],
            'numbering' => ['required', 'array'],
            'numbering.*' => ['required', 'string', 'max:80'],
        ], [
            'promotion_pass_mark.min' => 'The pass mark must be between 0 and 100.',
            'promotion_pass_mark.max' => 'The pass mark must be between 0 and 100.',
            'payout_account_number.regex' => 'Enter the 10-digit NUBAN account number.',
        ]);

        foreach ($numberingKeys as $key) {
            if (! array_key_exists($key, $data['numbering'])) {
                return back()->withErrors(["numbering.{$key}" => 'Every numbering pattern is required.'])->withInput();
            }
            if (! str_contains($data['numbering'][$key], '{SEQ')) {
                return back()->withErrors(["numbering.{$key}" => 'Include a {SEQ} or {SEQ:4} token so numbers stay unique.'])->withInput();
            }
        }

        SchoolSetting::store('admissions_open', $request->boolean('admissions_open'));
        SchoolSetting::store('show_positions', $request->boolean('show_positions'));
        SchoolSetting::store('promotion_pass_mark', (float) $data['promotion_pass_mark']);

        $hasPayout = filled($data['payout_bank_code'] ?? null)
            || filled($data['payout_account_number'] ?? null)
            || filled($data['payout_account_name'] ?? null);

        if ($hasPayout) {
            $request->validate([
                'payout_bank_code' => ['required', Rule::in(array_keys(NigerianBanks::options()))],
                'payout_account_number' => ['required', 'regex:/^\d{10}$/'],
                'payout_account_name' => ['required', 'string', 'max:120'],
            ], [
                'payout_account_number.regex' => 'Enter the 10-digit NUBAN account number.',
            ]);

            $syncPayout->handle(
                $school,
                (string) $data['payout_bank_code'],
                (string) $data['payout_account_number'],
                trim((string) $data['payout_account_name']),
            );
        }

        foreach ($data['numbering'] as $key => $pattern) {
            abort_unless(in_array($key, $numberingKeys, true), 422);

            $sequence = NumberSequence::query()->firstOrNew(['key' => $key]);
            $sequence->pattern = $pattern;
            if (! $sequence->exists) {
                $sequence->next_value = 1;
            }
            $sequence->save();
        }

        activity('school')->performedOn($school)->log('updated school settings');

        return back()->with('status', 'Settings saved.');
    }
}
