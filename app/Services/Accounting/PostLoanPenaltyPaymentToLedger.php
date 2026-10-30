<?php

namespace App\Services\Accounting;

use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Models\LoanPenaltyPayment;
use App\Support\Money;
use DomainException;

final class PostLoanPenaltyPaymentToLedger
{
    public function __construct(
        private readonly SocietyAssetAccounts $assets
    ) {
    }

    public function execute(
        LoanPenaltyPayment $payment
    ): LedgerTransaction {
        if (Money::toCents($payment->amount) <= 0) {
            throw new DomainException(
                'Penalty payment must be positive.'
            );
        }

        $penalty = $payment->loanPenalty;
        $loan = $penalty->loan;

        if (
            $loan->society_id !== $payment->society_id
            || $penalty->society_id !== $payment->society_id
        ) {
            throw new DomainException(
                'Loan penalty belongs to another society.'
            );
        }

        $cash = $this->assets->forMethod(
            $loan->society,
            $payment->payment_method
        );

        $income = LedgerAccount::firstOrCreate(
            [
                'society_id' => $payment->society_id,
                'code' => 'LOAN_PENALTY_INCOME',
            ],
            [
                'name' => 'Loan Penalty Income',
                'type' => 'income',
                'is_active' => true,
            ]
        );

        if ($income->type !== 'income' || ! $income->is_active) {
            throw new DomainException(
                'Loan penalty income account is unavailable.'
            );
        }

        $transaction = LedgerTransaction::create([
            'society_id' => $payment->society_id,
            'cycle_id' => $loan->cycle_id,
            'source_type' => 'loan_penalty_payment',
            'source_id' => $payment->id,
            'posting_date' => $payment->paid_on->toDateString(),
            'description' =>
                'Loan penalty payment ' . $loan->loan_number,
            'created_by' => $payment->recorded_by,
        ]);

        $transaction->lines()->create([
            'ledger_account_id' => $cash->id,
            'debit_amount' => $payment->amount,
            'credit_amount' => '0.00',
        ]);

        $transaction->lines()->create([
            'ledger_account_id' => $income->id,
            'debit_amount' => '0.00',
            'credit_amount' => $payment->amount,
        ]);

        return $transaction;
    }
}
