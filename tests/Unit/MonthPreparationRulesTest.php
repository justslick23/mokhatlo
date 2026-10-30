<?php

namespace Tests\Unit;

use App\Services\Operations\MonthPreparationRules as Rules;
use PHPUnit\Framework\TestCase;

final class MonthPreparationRulesTest extends TestCase
{
    public function test_due_day_clips_in_february_and_handles_leap_year(): void
    {
        self::assertSame('2026-02-28', Rules::dueDate('2026-02', 31));
        self::assertSame('2028-02-29', Rules::dueDate('2028-02', 31));
        self::assertSame('2026-11-28', Rules::dueDate('2026-11', 28));
    }

    public function test_never_retroactively_prepares_historical_months(): void
    {
        self::assertFalse(Rules::allowedMonth('2026-09', '2026-10-30', '2026-01-01', '2026-12-31'));
        self::assertTrue(Rules::allowedMonth('2026-10', '2026-10-30', '2026-01-01', '2026-12-31'));
        self::assertTrue(Rules::allowedMonth('2026-11', '2026-10-30', '2026-01-01', '2026-12-31'));
        self::assertFalse(Rules::allowedMonth('2027-01', '2026-10-30', '2026-01-01', '2026-12-31'));
    }

    public function test_mid_month_join_does_not_create_full_month_charge(): void
    {
        self::assertTrue(Rules::memberEligible('2026-10-01 14:00:00', null, '2026-10-01'));
        self::assertFalse(Rules::memberEligible('2026-10-20 09:00:00', null, '2026-10-01'));
        self::assertFalse(Rules::memberEligible('2026-08-01 09:00:00', '2026-09-30 12:00:00', '2026-10-01'));
    }
}
