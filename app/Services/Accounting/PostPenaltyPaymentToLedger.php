<?php

namespace App\Services\Accounting;

use App\Models\ContributionPenaltyPayment;
use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Support\Money;
use DomainException;

final class PostPenaltyPaymentToLedger
{
    public function execute(
        ContributionPenaltyPayment $payment
    ): LedgerTransaction {
        if (Money::toCents($payment->amount) <= 0) {
            throw new DomainException(
                'Penalty payment must be positive.'
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

        $assetName = match ($assetCode) {
            'CASH_ON_HAND' => 'Cash on Hand',
            'BANK_ACCOUNT' => 'Bank Account',
            'MOBILE_MONEY' => 'Mobile Money',
        };

        $asset = LedgerAccount::firstOrCreate(
            [
                'society_id' => $payment->society_id,
                'code' => $assetCode,
            ],
            [
                'name' => $assetName,
                'type' => 'asset',
                'is_active' => true,
            ]
        );

        $income = LedgerAccount::firstOrCreate(
            [
                'society_id' => $payment->society_id,
                'code' => 'CONTRIBUTION_PENALTY_INCOME',
            ],
            [
                'name' => 'Contribution Penalty Income',
                'type' => 'income',
                'is_active' => true,
            ]
        );

        $penalty = $payment->penalty;

        if ($penalty->society_id !== $payment->society_id) {
            throw new DomainException(
                'Penalty payment society mismatch.'
            );
        }

        $transaction = LedgerTransaction::create([
            'society_id' => $payment->society_id,
            'cycle_id' => $penalty->cycle_id,
            'source_type' => 'contribution_penalty_payment',
            'source_id' => $payment->id,
            'posting_date' => $payment->paid_on->toDateString(),
            'description' =>
                'Contribution penalty payment #' . $payment->id,
            'created_by' => $payment->recorded_by,
        ]);

        // Debit: Increase received cash / bank assets.
        $transaction->lines()->create([
            'ledger_account_id' => $asset->id,
            'debit_amount' => $payment->amount,
            'credit_amount' => '0.00',
        ]);

        // Credit: Recognise collected penalty income.
        $transaction->lines()->create([
            'ledger_account_id' => $income->id,
            'debit_amount' => '0.00',
            'credit_amount' => $payment->amount,
        ]);

        return $transaction;
    }
}
