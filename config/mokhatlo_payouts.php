
<?php

return [
    /*
     * These codes must match the accounts used by
     * your existing financial posting services.
     *
     * Do not create replacement accounts merely to
     * make a reconciliation error disappear.
     */
    'accounts' => [
        'savings_payable' => env(
            'MOKHATLO_SAVINGS_PAYABLE_ACCOUNT',
            'MEMBER_SAVINGS_PAYABLE'
        ),

        'interest_payable' => env(
            'MOKHATLO_INTEREST_PAYABLE_ACCOUNT',
            'MEMBER_INTEREST_PAYABLE'
        ),

        'contribution_penalty_income' => env(
            'MOKHATLO_CONTRIBUTION_PENALTY_ACCOUNT',
            'CONTRIBUTION_PENALTY_INCOME'
        ),

        'loan_penalty_income' => env(
            'MOKHATLO_LOAN_PENALTY_ACCOUNT',
            'LOAN_PENALTY_INCOME'
        ),

        'penalties_payable' => 'MEMBER_PENALTY_PAYABLE',
    ],
];
