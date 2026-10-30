<?php

namespace Tests\Unit;

use App\Services\Legacy\LegacyTransactionType;
use PHPUnit\Framework\TestCase;

class LegacyTransactionTypeTest extends TestCase
{
    public function test_legacy_loan_names_map_to_one_category(): void
    {
        $this->assertSame(
            'loan_disbursement',
            LegacyTransactionType::classify('loan_issued')
        );

        $this->assertSame(
            'loan_disbursement',
            LegacyTransactionType::classify('loan_disbursement')
        );
    }

    public function test_interest_is_not_an_independent_cash_receipt(): void
    {
        $this->assertSame(
            'interest_component',
            LegacyTransactionType::classify('interest')
        );

        $this->assertFalse(
            LegacyTransactionType::isIndependentCashMovement(
                'interest_component'
            )
        );
    }

    public function test_penalties_require_verification(): void
    {
        $this->assertTrue(
            LegacyTransactionType::needsReview(
                LegacyTransactionType::classify('penalty')
            )
        );
    }

    public function test_old_payouts_require_proof(): void
    {
        $this->assertTrue(
            LegacyTransactionType::needsReview(
                LegacyTransactionType::classify('year_end_payout')
            )
        );
    }

    public function test_contributions_are_receipts(): void
    {
        $this->assertTrue(
            LegacyTransactionType::isIndependentCashMovement(
                LegacyTransactionType::classify('contribution')
            )
        );
    }

    public function test_unknown_transaction_types_are_flagged(): void
    {
        $this->assertSame(
            'unknown',
            LegacyTransactionType::classify(
                'custom_historical_adjustment'
            )
        );
    }
}
