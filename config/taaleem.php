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
    | Subscription plans
    |--------------------------------------------------------------------------
    |
    | Every plan includes all features. Caps and prices are in Naira and can be
    | changed here (or via env) without a code change. Schools choose flat fee
    | (termly or annual) or per-student billing.
    |
    */

    'plans' => [
        'basic' => [
            'max_students' => (int) env('TAALEEM_STARTER_STUDENTS', 150),
            'max_staff' => (int) env('TAALEEM_STARTER_STAFF', 15),
            'flat_termly' => env('TAALEEM_STARTER_FLAT_TERM') ?: '45000',
            'flat_annual' => env('TAALEEM_STARTER_FLAT_YEAR') ?: '120000',
            'per_student' => env('TAALEEM_PRICE_BASIC') ?: '800',
            'description' => 'For small nursery and primary schools getting off paper and Excel.',
            'features' => [
                'All modules included',
                'Up to 150 students',
                'Up to 15 staff logins',
                'Email support',
            ],
        ],
        'standard' => [
            'max_students' => (int) env('TAALEEM_GROWTH_STUDENTS', 500),
            'max_staff' => (int) env('TAALEEM_GROWTH_STAFF', 40),
            'flat_termly' => env('TAALEEM_GROWTH_FLAT_TERM') ?: '95000',
            'flat_annual' => env('TAALEEM_GROWTH_FLAT_YEAR') ?: '250000',
            'per_student' => env('TAALEEM_PRICE_STANDARD') ?: '600',
            'description' => 'For growing primary and secondary schools that want everything in one place.',
            'features' => [
                'All modules included',
                'Up to 500 students',
                'Up to 40 staff logins',
                'Phone & WhatsApp support',
            ],
        ],
        'premium' => [
            'max_students' => (int) env('TAALEEM_CAMPUS_STUDENTS', 2000),
            'max_staff' => (int) env('TAALEEM_CAMPUS_STAFF', 120),
            'flat_termly' => env('TAALEEM_CAMPUS_FLAT_TERM') ?: '180000',
            'flat_annual' => env('TAALEEM_CAMPUS_FLAT_YEAR') ?: '480000',
            'per_student' => env('TAALEEM_PRICE_PREMIUM') ?: '450',
            'description' => 'For larger campuses with full staff and accounts teams.',
            'features' => [
                'All modules included',
                'Up to 2,000 students',
                'Up to 120 staff logins',
                'Dedicated account manager',
            ],
        ],
        'custom' => [
            'max_students' => null,
            'max_staff' => null,
            'flat_termly' => null,
            'flat_annual' => null,
            'per_student' => null,
            'description' => 'Negotiated seats and pricing.',
            'features' => ['Custom seats', 'Custom pricing', 'Priority support'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Marketing site
    |--------------------------------------------------------------------------
    |
    | Legacy per-student price env keys still work; plan cards prefer taaleem.plans.
    |
    */

    'marketing' => [
        'contact_email' => env('TAALEEM_CONTACT_EMAIL', 'hello@taaleem.ng'),
        'whatsapp_number' => env('TAALEEM_WHATSAPP_NUMBER'),
        'prices' => [
            'basic' => env('TAALEEM_PRICE_BASIC') ?: '800',
            'standard' => env('TAALEEM_PRICE_STANDARD') ?: '600',
            'premium' => env('TAALEEM_PRICE_PREMIUM') ?: '450',
        ],
    ],

];
