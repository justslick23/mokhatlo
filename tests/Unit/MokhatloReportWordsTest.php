<?php

namespace Tests\Unit;

use App\Support\MokhatloReportWords;
use PHPUnit\Framework\TestCase;

final class MokhatloReportWordsTest extends TestCase
{
    public function test_a_member_loan_payment_is_not_labelled_as_money_received(): void
    {
        $row = ['source' => 'repayment', 'direction' => 'in', 'detail' => 'Loan LEG-2-3 repaid'];
        self::assertSame('You paid', MokhatloReportWords::memberAction($row));
        self::assertSame('Money received', MokhatloReportWords::societyAction($row));
        self::assertSame('Loan payment', MokhatloReportWords::transactionType($row));
    }

    public function test_borrower_interest_is_not_treated_as_a_new_cash_payment(): void
    {
        $row = ['source' => 'interest_credit', 'direction' => 'noncash',
            'detail' => 'Refundable interest earned on LEG-2-3'];
        self::assertSame('You earned', MokhatloReportWords::memberAction($row));
        self::assertStringContainsString('not another payment', MokhatloReportWords::description($row));
    }

    public function test_public_accounts_use_plain_language(): void
    {
        self::assertSame('Total money held', MokhatloReportWords::holding('liquid_assets'));
        self::assertSame('Total savings belonging to members', MokhatloReportWords::holding('MEMBER_SAVINGS'));
    }
}
