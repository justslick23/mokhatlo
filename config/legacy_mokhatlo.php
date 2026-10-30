
<?php

return [
    'source_code' => 'legacy_mokhatlo',

    'connection' => [
        'driver' => 'mysql',
        'host' => env('LEGACY_DB_HOST', '127.0.0.1'),
        'port' => env('LEGACY_DB_PORT', '3306'),
        'database' => env('LEGACY_DB_DATABASE'),
        'username' => env('LEGACY_DB_USERNAME'),
        'password' => env('LEGACY_DB_PASSWORD'),
        'unix_socket' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => null,
    ],

    /*
     * Explicit fields from the original Mokhatlo schema.
     * Passwords and password-reset tokens are deliberately
     * excluded from staging.
     */
    'columns' => [
        'users' => [
            'id', 'name', 'email', 'email_verified_at',
            'created_at', 'updated_at',
        ],

        'societies' => [
            'id', 'name', 'description',
            'minimum_contribution', 'interest_rate',
            'penalty_type', 'penalty_value',
            'contribution_due_day',
            'repayment_period_months',
            'bank_account_reference',
            'created_at', 'updated_at', 'deleted_at',
        ],

        'members' => [
            'id', 'user_id', 'society_id',
            'role', 'status', 'joined_date',
            'created_at', 'updated_at', 'deleted_at',
        ],

        'cycles' => [
            'id', 'society_id', 'name',
            'start_date', 'end_date', 'status',
            'created_at', 'updated_at',
        ],

        'loans' => [
            'id', 'society_id', 'cycle_id',
            'member_id', 'principal', 'interest',
            'interest_paid', 'total_amount',
            'amount_repaid', 'outstanding_balance',
            'penalty_amount', 'issue_date', 'due_date',
            'status', 'purpose', 'issued_by',
            'created_at', 'updated_at', 'deleted_at',
        ],

        'transactions' => [
            'id', 'society_id', 'cycle_id',
            'member_id', 'loan_id', 'type', 'amount',
            'transaction_date', 'notes', 'recorded_by',
            'created_at', 'updated_at', 'deleted_at',
        ],

        'reminder_logs' => [
            'id', 'society_id', 'member_id', 'loan_id',
            'type', 'sent_at', 'created_at',
            'updated_at',
        ],
    ],

    'required_tables' => [
        'users',
        'societies',
        'members',
        'cycles',
        'loans',
        'transactions',
    ],
];
