<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Document numbering
    |--------------------------------------------------------------------------
    |
    | Default patterns for per-school numbering sequences. A school can change
    | its own pattern later; these seed the sequence the first time it is used.
    | Tokens: {SCHOOL} {YYYY} {YY} {SEQ:n}
    |
    */

    'numbering' => [
        'admission_no' => '{SCHOOL}/{YYYY}/{SEQ:4}',
        'staff_no' => '{SCHOOL}/STF/{SEQ:3}',
        'application_no' => 'APP/{YYYY}/{SEQ:4}',
        'invoice_no' => 'INV/{YY}/{SEQ:5}',
        'receipt_no' => 'RCT/{YY}/{SEQ:5}',
        'payslip_no' => 'PAY/{YY}/{SEQ:5}',
    ],

    /*
    |--------------------------------------------------------------------------
    | School setting defaults
    |--------------------------------------------------------------------------
    */

    'settings' => [
        'currency' => 'NGN',
        'date_format' => 'd/m/Y',
        'show_positions' => true,
        'promotion_pass_mark' => 40,
        'admissions_open' => true,
        'paystack_subaccount' => null,
        'payout_bank_code' => null,
        'payout_bank_name' => null,
        'payout_account_number' => null,
        'payout_account_name' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Trial length (days) for schools created by the platform
    |--------------------------------------------------------------------------
    */

    'trial_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Backups
    |--------------------------------------------------------------------------
    |
    | Where deploy/backup.sh writes its nightly files (~/backups of the app user),
    | so the platform Health page can show when the last one ran.
    |
    */

    'backup_path' => env('BACKUP_PATH', rtrim((string) (getenv('HOME') ?: storage_path('app')), '/').'/backups'),

    /*
    |--------------------------------------------------------------------------
    | Marketing site
    |--------------------------------------------------------------------------
    |
    | Prices are per student per term, in Naira. Leave a price as null until it
    | is decided; the pricing section then shows "Get a quote" for that plan.
    |
    */

    'marketing' => [
        'contact_email' => env('TAALEEM_CONTACT_EMAIL', 'hello@taaleem.ng'),
        'whatsapp_number' => env('TAALEEM_WHATSAPP_NUMBER'),
        'prices' => [
            'basic' => env('TAALEEM_PRICE_BASIC'),
            'standard' => env('TAALEEM_PRICE_STANDARD'),
            'premium' => env('TAALEEM_PRICE_PREMIUM'),
        ],
    ],

];
