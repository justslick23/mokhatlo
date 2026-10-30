<?php

namespace Tests\Unit;

use App\Services\MokhatloMail\MonthlyPenaltyShares;
use PHPUnit\Framework\TestCase;

final class MonthlyPenaltySharingMathTest extends TestCase
{
    public function test_equal_four_way_share_of_fifty_maloti(): void
    {
        $this->assertSame([1 => 1250, 2 => 1250, 3 => 1250, 4 => 1250],
            MonthlyPenaltyShares::splitEvenly(5000, [4, 2, 3, 1]));
    }

    public function test_uneven_cents_are_not_lost_or_created(): void
    {
        $shares = MonthlyPenaltyShares::splitEvenly(1001, [9, 3, 7]);
        $this->assertSame([3 => 334, 7 => 334, 9 => 333], $shares);
        $this->assertSame(1001, array_sum($shares));
    }

    public function test_duplicate_member_ids_do_not_get_double_shares(): void
    {
        $this->assertSame([2 => 5, 4 => 5],
            MonthlyPenaltyShares::splitEvenly(10, [4, 2, 4]));
    }

    public function test_empty_eligible_members_do_not_divide_by_zero(): void
    {
        $this->assertSame([], MonthlyPenaltyShares::splitEvenly(5000, []));
    }
}
