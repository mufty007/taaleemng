<?php

namespace App\Support\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Platform Paystack: fee checkout and school payout subaccounts.
 *
 * @see https://paystack.com/docs/api/transaction/
 * @see https://paystack.com/docs/api/subaccount/
 */
class Paystack
{
    public function isConfigured(): bool
    {
        return filled(config('services.paystack.secret_key'));
    }

    /**
     * Whether a school must have its own payout subaccount before parents can pay it.
     * Always in production, so a school's fees never settle anywhere but its own bank.
     */
    public function requiresSubaccount(): bool
    {
        return app()->isProduction() || (bool) config('services.paystack.require_subaccount', true);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{authorization_url: string, access_code: string, reference: string}
     */
    public function initialize(string $email, int $amount, string $reference, string $callbackUrl, array $metadata, ?string $subaccount = null): array
    {
        return $this->client()->post('/transaction/initialize', array_filter([
            'email' => $email,
            'amount' => $amount,
            'currency' => 'NGN',
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
            'subaccount' => $subaccount,
        ]))->throw()->json('data');
    }

    /**
     * Create a Paystack subaccount so settlements land in the school's bank.
     *
     * @return array{subaccount_code: string, ...}
     */
    public function createSubaccount(string $businessName, string $settlementBank, string $accountNumber, string $percentageCharge = '0'): array
    {
        return (array) $this->client()->post('/subaccount', [
            'business_name' => $businessName,
            'settlement_bank' => $settlementBank,
            'account_number' => $accountNumber,
            'percentage_charge' => $percentageCharge,
        ])->throw()->json('data');
    }

    /**
     * @return array{subaccount_code: string, ...}
     */
    public function updateSubaccount(string $code, string $businessName, string $settlementBank, string $accountNumber, string $percentageCharge = '0'): array
    {
        return (array) $this->client()->put('/subaccount/'.rawurlencode($code), [
            'business_name' => $businessName,
            'settlement_bank' => $settlementBank,
            'account_number' => $accountNumber,
            'percentage_charge' => $percentageCharge,
        ])->throw()->json('data');
    }

    /**
     * Ask Paystack what happened to a payment. Never trust the browser or the
     * webhook body alone; this is the answer that counts.
     *
     * @return array<string, mixed>
     */
    public function verify(string $reference): array
    {
        return (array) $this->client()->get('/transaction/verify/'.rawurlencode($reference))->throw()->json('data');
    }

    /**
     * The account's balances; used to check that the secret key works.
     *
     * @return list<array{currency: string, balance: int}>
     */
    public function balance(): array
    {
        return (array) $this->client()->get('/balance')->throw()->json('data');
    }

    public function hasValidSignature(string $payload, ?string $signature): bool
    {
        if (! $this->isConfigured() || blank($signature)) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $payload, (string) config('services.paystack.secret_key')), $signature);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.paystack.base_url'))
            ->withToken((string) config('services.paystack.secret_key'))
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 500, throw: false);
    }
}
