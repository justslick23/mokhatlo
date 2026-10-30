<?php

namespace App\Services\Accounting;

use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Models\LoanDisbursement;
use App\Models\LoanInterestCredit;
use App\Models\LoanRepayment;
use App\Support\Money;
use DomainException;

final class PostLoanToLedger
{
    public function __construct(
        private readonly SocietyAssetAccounts $assets
    ) {}

    private function principalAccount(
        int $societyId
    ): LedgerAccount {
        $account = LedgerAccount::firstOrCreate(
            [
                'society_id' => $societyId,
                'code' => 'LOAN_PRINCIPAL_RECEIVABLE',
            ],
            [
                'name' => 'Loan Principal Receivable',
                'type' => 'asset',
                'is_active' => true,
            ]
        );

        if ($account->type !== 'asset' || ! $account->is_active) {
            throw new DomainException(
                'Loan principal receivable account is unavailable.'
            );
        }

        return $account;
    }

    private function interestPayableAccount(
        int $societyId
    ): LedgerAccount {
        $account = LedgerAccount::firstOrCreate(
            [
                'society_id' => $societyId,
                'code' => 'MEMBER_INTEREST_PAYABLE',
            ],
            [
                'name' => 'Member Loan Interest Payable',
                'type' => 'liability',
                'is_active' => true,
            ]
        );

        if (
            $account->type !== 'liability'
            || ! $account->is_active
        ) {
            throw new DomainException(
                'Member interest payable account is unavailable.'
            );
        }

        return $account;
    }

    public function disbursement(
        LoanDisbursement $disbursement
    ): LedgerTransaction {
        $loan = $disbursement->loan;
        $society = $loan->society;

        if (
            (int) $loan->society_id
            !== (int) $disbursement->society_id
        ) {
            throw new DomainException(
                'Disbursement belongs to another society.'
            );
        }

        $cash = $this->assets->forMethod(
            $society,
            $disbursement->payment_method
        );

        $receivable = $this->principalAccount(
            $society->id
        );

        $transaction = LedgerTransaction::create([
            'society_id' => $society->id,
            'cycle_id' => $loan->cycle_id,
            'source_type' => 'loan_disbursement',
            'source_id' => $disbursement->id,
            'posting_date' =>
                $disbursement->disbursed_on->toDateString(),
            'description' =>
                'Loan disbursement ' . $loan->loan_number,
            'created_by' => $disbursement->recorded_by,
        ]);

        // Debit loan receivable.
        $transaction->lines()->create([
            'ledger_account_id' => $receivable->id,
            'debit_amount' => $disbursement->amount,
            'credit_amount' => '0.00',
        ]);

        // Credit the account releasing the funds.
        $transaction->lines()->create([
            'ledger_account_id' => $cash->id,
            'debit_amount' => '0.00',
            'credit_amount' => $disbursement->amount,
        ]);

        return $transaction;
    }

    public function repayment(
        LoanRepayment $repayment,
        int $principalCents,
        int $interestCents
    ): LedgerTransaction {
        $totalCents = Money::toCents(
            $repayment->amount
        );

        if (
            $totalCents <= 0
            || $principalCents < 0
            || $interestCents < 0
            || $totalCents !== $principalCents + $interestCents
        ) {
            throw new DomainException(
                'Loan repayment allocation is not balanced.'
            );
        }

        $loan = $repayment->loan;
        $society = $loan->society;

        if (
            (int) $repayment->society_id
            !== (int) $loan->society_id
        ) {
            throw new DomainException(
                'Repayment belongs to another society.'
            );
        }

        $cash = $this->assets->forMethod(
            $society,
            $repayment->payment_method
        );

        $transaction = LedgerTransaction::create([
            'society_id' => $society->id,
            'cycle_id' => $loan->cycle_id,
            'source_type' => 'loan_repayment',
            'source_id' => $repayment->id,
            'posting_date' =>
                $repayment->paid_on->toDateString(),
            'description' =>
                'Loan repayment ' . $loan->loan_number,
            'created_by' => $repayment->recorded_by,
        ]);

        // Full amount of money received.
        $transaction->lines()->create([
            'ledger_account_id' => $cash->id,
            'debit_amount' => $repayment->amount,
            'credit_amount' => '0.00',
        ]);

        // Repayment of money lent is not profit.
        if ($principalCents > 0) {
            $receivable = $this->principalAccount(
                $society->id
            );

            $transaction->lines()->create([
                'ledger_account_id' => $receivable->id,
                'debit_amount' => '0.00',
                'credit_amount' => Money::fromCents(
                    $principalCents
                ),
            ]);
        }

        /*
         * Mokhatlo rule:
         * Interest actually paid belongs to the borrower,
         * to be returned at the annual payout.
         */
        if ($interestCents > 0) {
            $borrower = $loan->membership;

            if (
                ! $borrower
                || (int) $borrower->society_id
                    !== (int) $society->id
            ) {
                throw new DomainException(
                    'Borrower membership could not be verified.'
                );
            }

            $interestPayable = $this->interestPayableAccount(
                $society->id
            );

            $interestAmount = Money::fromCents(
                $interestCents
            );

            // Increase amount owed to members.
            $transaction->lines()->create([
                'ledger_account_id' => $interestPayable->id,
                'debit_amount' => '0.00',
                'credit_amount' => $interestAmount,
            ]);

            // Assign entitlement to the borrower.
            LoanInterestCredit::create([
                'society_id' => $society->id,
                'loan_id' => $loan->id,
                'loan_repayment_id' => $repayment->id,
                'beneficiary_membership_id' => $borrower->id,
                'amount' => $interestAmount,
            ]);
        }

        return $transaction;
    }
}
