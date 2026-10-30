<?php

namespace Tests\Unit;

use App\Services\MokhatloMail\MonthlyStatementBuilder;
use PHPUnit\Framework\TestCase;

final class MonthlyReportBoundaryTest extends TestCase
{
    public function test_report_bounds_are_month_exact_and_handle_leap_year(): void
    {
        $this->assertSame(['2026-02-01','2026-02-28'], MonthlyStatementBuilder::bounds('2026-02'));
        $this->assertSame(['2024-02-01','2024-02-29'], MonthlyStatementBuilder::bounds('2024-02'));
        $this->assertSame(['2026-10-01','2026-10-31'], MonthlyStatementBuilder::bounds('2026-10'));
    }

    public function test_invalid_month_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MonthlyStatementBuilder::bounds('2026-13');
    }
}
