<?php

namespace App\Services\Accounting;

use App\Models\ContributionPayment;
use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Support\Money;
use DomainException;

final class PostContributionToLedger
{
    private const ACCOUNT_DEFINITIONS = [
        'CASH_ON_HAND' => [
            'name' => 'Cash on Hand',
            'type' => 'asset',
        ],
        'BANK_ACCOUNT' => [
            'name' => 'Bank Account',
            'type' => 'asset',
        ],
        'MOBILE_MONEY' => [
            'name' => 'Mobile Money',
            'type' => 'asset',
        ],
        'MEMBER_SAVINGS' => [
            'name' => 'Member Savings Liability',
            'type' => 'liability',
        ],
    ];

    public function execute(
        ContributionPayment $payment
    ): LedgerTransaction {
        $amountCents = Money::toCents(
            $payment->amount
        );

        if ($amountCents <= 0) {
            throw new DomainException(
                'Ledger amounts must be positive.'
            );
        }

        $assetCode = match ($payment->payment_method) {
            'cash' => 'CASH_ON_HAND',
            'bank_transfer' => 'BANK_ACCOUNT',
            'mobile_money' => 'MOBILE_MONEY',
            default => throw new DomainException(
                'Unsupported payment method.'
            ),
        };

        $accounts = [];

        foreach (self::ACCOUNT_DEFINITIONS as $code => $details) {
            $accounts[$code] = LedgerAccount::firstOrCreate(
                [
                    'society_id' => $payment->society_id,
                    'code' => $code,
                ],
                [
                    'name' => $details['name'],
                    'type' => $details['type'],
                    'is_active' => true,
                ]
            );
        }

        $transaction = LedgerTransaction::create([
            'society_id' => $payment->society_id,
            'cycle_id' => $payment->cycle_id,
            'source_type' => 'contribution_payment',
            'source_id' => $payment->id,
            'posting_date' => $payment->paid_on->toDateString(),
            'description' => 'Member contribution #' . $payment->id,
            'created_by' => $payment->recorded_by,
        ]);

        // Debit the asset account receiving the money.
        $transaction->lines()->create([
            'ledger_account_id' => $accounts[$assetCode]->id,
            'debit_amount' => $payment->amount,
            'credit_amount' => '0.00',
        ]);

        // Credit the liability owed to members.
        $transaction->lines()->create([
            'ledger_account_id' => $accounts['MEMBER_SAVINGS']->id,
            'debit_amount' => '0.00',
            'credit_amount' => $payment->amount,
        ]);

        return $transaction;
    }
}
