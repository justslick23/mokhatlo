<?php

namespace App\Services\Loans;

use App\Models\Loan;
use Carbon\CarbonImmutable;
use DomainException;

final class GenerateLoanSchedule
{
    public function execute(
        Loan $loan,
        string $disbursedOn
    ): void {
        if ($loan->installments()->exists()) {
            throw new DomainException(
                'A repayment obligation already exists for this loan.'
            );
        }

        $termMonths = (int) $loan->term_months;

        if ($termMonths < 1 || $termMonths > 60) {
            throw new DomainException(
                'Invalid loan maturity period.'
            );
        }

        $maturityDate = CarbonImmutable::parse(
            $disbursedOn
        )
            ->addMonthsNoOverflow($termMonths)
            ->toDateString();

        $loan->update([
            'matures_on' => $maturityDate,
        ]);

        $loan->installments()->create([
            'society_id' => $loan->society_id,
            'installment_number' => 1,
            'due_date' => $maturityDate,
            'principal_due' => $loan->principal_amount,
            'interest_due' => $loan->quoted_interest_amount,
            'total_due' => $loan->quoted_total_amount,
        ]);
    }
}
