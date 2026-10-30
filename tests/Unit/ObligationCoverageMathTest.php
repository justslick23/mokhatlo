<?php

namespace Tests\Unit;

use App\Services\Contributions\ObligationCoverageMath;
use PHPUnit\Framework\TestCase;

final class ObligationCoverageMathTest extends TestCase
{
    public function test_full_historical_coverage(): void
    {
        $a = ObligationCoverageMath::calculate(50000, 0, 50000);
        self::assertTrue($a['fully_paid']);
        self::assertSame(0, $a['outstanding_cents']);
        self::assertSame(50000, $a['paid_cents']);
    }

    public function test_combines_direct_and_historical_without_dropping_either(): void
    {
        $a = ObligationCoverageMath::calculate(50000, 15000, 20000);
        self::assertSame(35000, $a['paid_cents']);
        self::assertSame(15000, $a['outstanding_cents']);
    }

    public function test_overpayment_is_retained_without_negative_outstanding(): void
    {
        $a = ObligationCoverageMath::calculate(50000, 0, 70000);
        self::assertSame(0, $a['outstanding_cents']);
        self::assertSame(20000, $a['excess_cents']);
    }

    public function test_rejects_negative_inputs(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ObligationCoverageMath::calculate(50000, -100, 0);
    }
}
