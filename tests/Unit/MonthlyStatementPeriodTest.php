<?php

namespace Tests\Unit;

use App\Services\MokhatloMail\MonthlyStatementBuilder;
use PHPUnit\Framework\TestCase;

final class MonthlyStatementPeriodTest extends TestCase
{
    public function test_month_bounds_include_leap_day(): void
    {
        $this->assertSame(['2028-02-01', '2028-02-29'], MonthlyStatementBuilder::bounds('2028-02'));
    }

    public function test_month_bounds_include_october_2026(): void
    {
        $this->assertSame(['2026-10-01', '2026-10-31'], MonthlyStatementBuilder::bounds('2026-10'));
    }

    public function test_invalid_month_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MonthlyStatementBuilder::bounds('2026-13');
    }
}
