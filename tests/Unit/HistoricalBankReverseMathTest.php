<?php

namespace Tests\Unit;

use App\Services\MokhatloMail\HistoricalBankBalanceForReport;
use PHPUnit\Framework\TestCase;

final class HistoricalBankReverseMathTest extends TestCase
{
    public function test_september_balance_is_reconstructed_by_reversing_later_movements(): void
    {
        // Reconciled at 29 October: M11,790; net new funds after September: M2,200.
        $this->assertSame(959000, HistoricalBankBalanceForReport::reverseMovements(1179000, 220000));
    }

    public function test_reclassified_cash_is_not_counted_twice(): void
    {
        $this->assertSame(1179000, HistoricalBankBalanceForReport::reverseMovements(1179000, 0));
    }
}
