<?php

namespace Tests\Unit;

use App\Services\Legacy\LegacyObligationReviewRules;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LegacyObligationReviewRulesTest extends TestCase
{
    public function test_valid_historical_obligation_dates(): void
    {
        LegacyObligationReviewRules::assertObligationDate('2026-04-01', '2026-04-30', '2026-01-01', '2026-12-31');
        $this->assertTrue(true);
    }

    public function test_rejects_due_date_in_another_month(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LegacyObligationReviewRules::assertObligationDate('2026-04-01', '2026-05-01', '2026-01-01', '2026-12-31');
    }

    public function test_rejects_invalid_calendar_date(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LegacyObligationReviewRules::assertObligationDate('2026-02-01', '2026-02-30', '2026-01-01', '2026-12-31');
    }

    public function test_rejects_out_of_cycle_period(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LegacyObligationReviewRules::assertObligationDate('2027-01-01', '2027-01-10', '2026-01-01', '2026-12-31');
    }

    public function test_one_receipt_can_cover_multiple_months_without_overallocation(): void
    {
        LegacyObligationReviewRules::assertAmount(50000, 120000, 70000);
        $this->assertTrue(true);
    }

    public function test_refuses_to_overallocate_receipt(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LegacyObligationReviewRules::assertAmount(50001, 120000, 70000);
    }
}
