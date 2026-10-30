<?php

namespace Tests\Unit;

use App\Services\Legacy\LegacyReceiptPeriodRules;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class LegacyReceiptPeriodRulesTest extends TestCase
{
    public function test_month_is_normalized_to_first(): void
    {
        $this->assertSame('2026-04-01', LegacyReceiptPeriodRules::monthStart('2026-04'));
    }

    public function test_bad_month_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LegacyReceiptPeriodRules::monthStart('2026-13');
    }

    public function test_partial_receipt_allocation_is_valid(): void
    {
        LegacyReceiptPeriodRules::assertMayAttribute(100000, 40000, 60000);
        $this->assertSame(60000, LegacyReceiptPeriodRules::available(100000, 40000));
    }

    public function test_overallocation_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LegacyReceiptPeriodRules::assertMayAttribute(100000, 90000, 20000);
    }

    public function test_month_must_overlap_cycle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LegacyReceiptPeriodRules::assertWithinCycle('2027-01-01', '2026-01-01', '2026-12-31');
    }
}
