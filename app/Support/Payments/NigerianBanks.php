<?php

namespace App\Support\Payments;

/**
 * Common Nigerian bank codes accepted by Paystack settlement.
 *
 * @see https://paystack.com/docs/api/miscellaneous/#list-banks
 */
final class NigerianBanks
{
    /**
     * @return array<string, string> code => name
     */
    public static function options(): array
    {
        return [
            '044' => 'Access Bank',
            '023' => 'Citibank Nigeria',
            '050' => 'Ecobank Nigeria',
            '070' => 'Fidelity Bank',
            '011' => 'First Bank of Nigeria',
            '214' => 'First City Monument Bank',
            '058' => 'Guaranty Trust Bank',
            '030' => 'Heritage Bank',
            '301' => 'Jaiz Bank',
            '082' => 'Keystone Bank',
            '526' => 'Parallex Bank',
            '076' => 'Polaris Bank',
            '101' => 'Providus Bank',
            '221' => 'Stanbic IBTC Bank',
            '068' => 'Standard Chartered Bank',
            '232' => 'Sterling Bank',
            '100' => 'Suntrust Bank',
            '032' => 'Union Bank of Nigeria',
            '033' => 'United Bank For Africa',
            '215' => 'Unity Bank',
            '035' => 'Wema Bank',
            '057' => 'Zenith Bank',
        ];
    }

    public static function name(string $code): ?string
    {
        return self::options()[$code] ?? null;
    }
}
