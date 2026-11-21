<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\NumberSequence;
use App\Models\SchoolSetting;
use App\Support\Numbering\SequenceGenerator;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * School-wide preferences: admissions, promotion, payments and document numbers.
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

    public function edit(CurrentSchool $currentSchool, SequenceGenerator $sequences): View
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
            'paystackSubaccount' => SchoolSetting::valueFor('paystack_subaccount'),
            'numbering' => $numbering,
        ]);
    }

    public function update(Request $request, CurrentSchool $currentSchool): RedirectResponse
    {
        $school = $currentSchool->getOrFail();
        $numberingKeys = array_keys(config('taaleem.numbering'));

        $data = $request->validate([
            'admissions_open' => ['sometimes', 'boolean'],
            'show_positions' => ['sometimes', 'boolean'],
            'promotion_pass_mark' => ['required', 'numeric', 'min:0', 'max:100'],
            'paystack_subaccount' => ['nullable', 'string', 'max:80'],
            'numbering' => ['required', 'array'],
            'numbering.*' => ['required', 'string', 'max:80'],
        ], [
            'promotion_pass_mark.min' => 'The pass mark must be between 0 and 100.',
            'promotion_pass_mark.max' => 'The pass mark must be between 0 and 100.',
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
        SchoolSetting::store('paystack_subaccount', filled($data['paystack_subaccount'] ?? null) ? trim($data['paystack_subaccount']) : null);

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
