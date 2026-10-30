<?php

namespace App\Services\Loans;

use App\Support\Money;
use App\Support\Percentage;
use DomainException;

final class QuoteFlatLoan
{
    public function calculate(
        string $principal,
        string $rate,
        string $basis,
        int $termMonths
    ): array {
        $principalCents = Money::toCents($principal);

        // Conservative upper bound for the first release.
        $maximum = Money::toCents('9999999999.99');

        if (
            $principalCents <= 0
            || $principalCents > $maximum
        ) {
            throw new DomainException(
                'Loan principal is outside the supported range.'
            );
        }

        if ($termMonths < 1 || $termMonths > 60) {
            throw new DomainException(
                'Invalid loan repayment period.'
            );
        }

        if (! in_array(
            $basis,
            ['per_term', 'per_month'],
            true
        )) {
            throw new DomainException(
                'Unsupported interest basis.'
            );
        }

        $interestPerPeriod = Percentage::applyToCents(
            $principalCents,
            $rate
        );

        /*
        | Flat Interest
        |
        | Per term:
        | Interest = principal × rate
        |
        | Per month:
        | Interest = rounded monthly interest × months
        |
        | The original principal is used throughout.
        */
        $interestCents = $basis === 'per_term'
            ? $interestPerPeriod
            : $interestPerPeriod * $termMonths;

        $totalCents = $principalCents + $interestCents;

        return [
            'principal_amount' =>
                Money::fromCents($principalCents),

            'interest_amount' =>
                Money::fromCents($interestCents),

            'total_amount' =>
                Money::fromCents($totalCents),

            'term_months' => $termMonths,
            'interest_rate_percent' => $rate,
            'interest_method' => 'flat',
            'interest_basis' => $basis,
        ];
    }
}
