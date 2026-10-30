<?php

return [
    // Map these to the exact account codes used by your existing posting services.
    // Do NOT change production ledger account codes just to make a preflight pass.
    'accounts' => [
        'savings_payable' => env(
    'LEGACY_SAVINGS_PAYABLE_CODE',
    'MEMBER_SAVINGS'
),
        'principal_receivable' => env('LEGACY_LOANS_RECEIVABLE_CODE', 'LOAN_PRINCIPAL_RECEIVABLE'),
        'interest_payable' => env('LEGACY_INTEREST_PAYABLE_CODE', 'MEMBER_INTEREST_PAYABLE'),
        'clearing' => 'LEGACY_IMPORT_CLEARING',
    ],
];
