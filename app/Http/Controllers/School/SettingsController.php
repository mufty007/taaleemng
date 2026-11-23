<?php

namespace App\Http\Controllers\School;

use App\Actions\Academic\MergeClassArms;
use App\Actions\Fees\SyncSchoolPayoutSubaccount;
use App\Enums\ClassStructure;
use App\Enums\SchoolInvoiceStatus;
use App\Enums\SchoolRole;
use App\Http\Controllers\Controller;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\NumberSequence;
use App\Models\SchoolInvoice;
use App\Models\SchoolSetting;
use App\Support\Numbering\SequenceGenerator;
use App\Support\Payments\NigerianBanks;
use App\Support\Payments\Paystack;
use App\Support\Schools\RoleOnboarding;
use App\Support\Schools\SetupChecklist;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * School-wide preferences: admissions, promotion, payouts, class structure,
 * onboarding copy and document numbers.
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
            'subscriptionInvoices' => SchoolInvoice::query()->where('school_id', $school->id)
                ->whereIn('status', [SchoolInvoiceStatus::Sent, SchoolInvoiceStatus::Paid])
                ->latest('issued_on')->limit(4)->get(),
            'platformPaystackReady' => $paystack->isConfigured(),
            'banks' => NigerianBanks::options(),
            'numbering' => $numbering,
            'classStructures' => ClassStructure::options(),
            'setupDefaults' => SetupChecklist::defaults(),
            'roleDefaults' => RoleOnboarding::defaults(),
            'roleLabels' => collect(SchoolRole::cases())->mapWithKeys(fn (SchoolRole $role) => [$role->value => $role->label()])->all(),
        ]);
    }

    public function update(
        Request $request,
        CurrentSchool $currentSchool,
        SyncSchoolPayoutSubaccount $syncPayout,
        MergeClassArms $mergeClassArms,
    ): RedirectResponse {
        $school = $currentSchool->getOrFail();
        $numberingKeys = array_keys(config('taaleem.numbering'));
        $setupKeys = array_keys(SetupChecklist::defaults());
        $roleKeys = array_keys(RoleOnboarding::defaults());

        $data = $request->validate([
            'admissions_open' => ['sometimes', 'boolean'],
            'show_positions' => ['sometimes', 'boolean'],
            'promotion_pass_mark' => ['required', 'numeric', 'min:0', 'max:100'],
            'class_structure' => ['required', Rule::enum(ClassStructure::class)],
            'merge_arms' => ['sometimes', 'boolean'],
            'payout_bank_code' => ['nullable', 'string', Rule::in(array_keys(NigerianBanks::options()))],
            'payout_account_number' => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'payout_account_name' => ['nullable', 'string', 'max:120'],
            'numbering' => ['required', 'array'],
            'numbering.*' => ['required', 'string', 'max:80'],
            'setup' => ['nullable', 'array'],
            'setup.*.enabled' => ['sometimes', 'boolean'],
            'setup.*.title' => ['nullable', 'string', 'max:120'],
            'setup.*.body' => ['nullable', 'string', 'max:255'],
            'role_onboarding' => ['nullable', 'array'],
            'role_onboarding.*.enabled' => ['sometimes', 'boolean'],
            'role_onboarding.*.title' => ['nullable', 'string', 'max:120'],
            'role_onboarding.*.steps' => ['nullable', 'string', 'max:2000'],
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

        $structure = ClassStructure::from($data['class_structure']);
        $hasMultipleArms = ClassArm::query()
            ->select('class_level_id')
            ->groupBy('class_level_id')
            ->havingRaw('count(*) > 1')
            ->exists();
        $needsMerge = $structure === ClassStructure::Single && $hasMultipleArms;

        if ($needsMerge && ! $request->boolean('merge_arms')) {
            return back()->withErrors([
                'class_structure' => 'You have A/B arms today. Tick “Merge arms into one class per level” to switch to single classes, or stay on A, B, C arms.',
            ])->withInput();
        }

        SchoolSetting::store('admissions_open', $request->boolean('admissions_open'));
        SchoolSetting::store('show_positions', $request->boolean('show_positions'));
        SchoolSetting::store('promotion_pass_mark', (float) $data['promotion_pass_mark']);

        $setupChecklist = [];
        foreach ($setupKeys as $key) {
            $row = $data['setup'][$key] ?? [];
            $setupChecklist[$key] = [
                'enabled' => $request->boolean("setup.{$key}.enabled"),
                'title' => filled($row['title'] ?? null) ? trim((string) $row['title']) : null,
                'body' => filled($row['body'] ?? null) ? trim((string) $row['body']) : null,
            ];
        }

        $roleOnboarding = [];
        foreach ($roleKeys as $key) {
            $row = $data['role_onboarding'][$key] ?? [];
            $steps = filled($row['steps'] ?? null)
                ? array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $row['steps']) ?: [])))
                : null;
            $roleOnboarding[$key] = [
                'enabled' => $request->boolean("role_onboarding.{$key}.enabled"),
                'title' => filled($row['title'] ?? null) ? trim((string) $row['title']) : null,
                'steps' => $steps,
            ];
        }

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

        DB::transaction(function () use ($school, $structure, $needsMerge, $mergeClassArms, $setupChecklist, $roleOnboarding): void {
            if ($needsMerge || $structure === ClassStructure::Single) {
                ClassLevel::query()->with('arms')->orderBy('position')->each(function (ClassLevel $level) use ($mergeClassArms): void {
                    $byStream = $level->arms->groupBy(fn (ClassArm $arm) => $arm->stream_id ?? 'none');

                    if ($byStream->contains(fn ($group) => $group->count() > 1)) {
                        $mergeClassArms->handle($level);

                        return;
                    }

                    // One arm per stream (or one class with no stream): drop the letter.
                    // Streamed singles keep the stream name so (class_level_id, name) stays unique.
                    $level->arms->each(function (ClassArm $arm): void {
                        $arm->loadMissing('stream');
                        $arm->update(['name' => $arm->stream?->name ?? '']);
                    });
                });
            }

            $school->update([
                'class_structure' => $structure,
                'setup_checklist' => $setupChecklist,
                'role_onboarding' => $roleOnboarding,
            ]);
        });

        activity('school')->performedOn($school)->log('updated school settings');

        return back()->with('status', 'Settings saved.');
    }
}
