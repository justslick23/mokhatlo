<?php

namespace App\Services\Loans;

use App\Models\Loan;
use App\Support\Money;
use DomainException;

final class LoanBalances
{
    public function summary(Loan $loan): array
    {
        $installments = $loan->installments()
            ->with('allocations')
            ->get();

        $principalDue = 0;
        $interestDue = 0;
        $principalPaid = 0;
        $interestPaid = 0;

        foreach ($installments as $installment) {
            $principalDue += Money::toCents(
                $installment->principal_due
            );

            $interestDue += Money::toCents(
                $installment->interest_due
            );

            foreach ($installment->allocations as $allocation) {
                $principalPaid += Money::toCents(
                    $allocation->principal_amount
                );

                $interestPaid += Money::toCents(
                    $allocation->interest_amount
                );
            }
        }

        if (
            $principalPaid > $principalDue
            || $interestPaid > $interestDue
        ) {
            throw new DomainException(
                'Loan allocations exceed contractual amounts.'
            );
        }

        return [
            'principal_due' => $principalDue,
            'interest_due' => $interestDue,
            'principal_paid' => $principalPaid,
            'interest_paid' => $interestPaid,
            'principal_outstanding' => $principalDue - $principalPaid,
            'interest_outstanding' => $interestDue - $interestPaid,
            'total_outstanding' =>
                ($principalDue - $principalPaid)
                + ($interestDue - $interestPaid),
        ];
    }
}
