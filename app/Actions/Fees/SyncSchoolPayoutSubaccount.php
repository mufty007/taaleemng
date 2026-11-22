<?php

namespace App\Actions\Fees;

use App\Models\School;
use App\Models\SchoolSetting;
use App\Support\Payments\NigerianBanks;
use App\Support\Payments\Paystack;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Create or refresh the school's Paystack subaccount from bank details so fee
 * settlements land in the school's account. Uses the platform Paystack keys.
 */
final class SyncSchoolPayoutSubaccount
{
    public function __construct(private Paystack $paystack) {}

    public function handle(School $school, string $bankCode, string $accountNumber, string $accountName): string
    {
        if (! $this->paystack->isConfigured()) {
            throw ValidationException::withMessages([
                'payout_bank_code' => 'Online payments are not configured on the platform yet. Contact Ta’aleem support.',
            ]);
        }

        $bankName = NigerianBanks::name($bankCode);

        if ($bankName === null) {
            throw ValidationException::withMessages(['payout_bank_code' => 'Choose a supported Nigerian bank.']);
        }

        $existing = SchoolSetting::valueFor('paystack_subaccount');

        try {
            $data = filled($existing)
                ? $this->paystack->updateSubaccount((string) $existing, $school->name, $bankCode, $accountNumber)
                : $this->paystack->createSubaccount($school->name, $bankCode, $accountNumber);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'payout_account_number' => 'Paystack could not verify that bank account. Check the number and try again.',
            ]);
        }

        $code = (string) ($data['subaccount_code'] ?? $existing);

        if ($code === '') {
            throw ValidationException::withMessages([
                'payout_account_number' => 'Paystack did not return a subaccount. Try again or contact support.',
            ]);
        }

        SchoolSetting::store('payout_bank_code', $bankCode);
        SchoolSetting::store('payout_bank_name', $bankName);
        SchoolSetting::store('payout_account_number', $accountNumber);
        SchoolSetting::store('payout_account_name', $accountName);
        SchoolSetting::store('paystack_subaccount', $code);

        return $code;
    }
}
