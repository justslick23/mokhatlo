<?php

namespace App\Services\Operations;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** Calendar and eligibility rules for NEW operating-month obligations only. */
final class MonthPreparationRules
{
    public static function bounds(string $month): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month)) {
            throw new InvalidArgumentException('Month must be YYYY-MM.');
        }
        $first = CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01');
        if (! $first || $first->format('Y-m') !== $month) {
            throw new InvalidArgumentException('Invalid calendar month.');
        }
        return [$first->toDateString(), $first->endOfMonth()->toDateString()];
    }

    public static function dueDate(string $month, int $dueDay): string
    {
        if ($dueDay < 1 || $dueDay > 31) {
            throw new InvalidArgumentException('Policy due day must be 1–31.');
        }
        $first = CarbonImmutable::parse(self::bounds($month)[0]);
        return $first->day(min($dueDay, $first->daysInMonth))->toDateString();
    }

    /** Do not silently impose a full-month obligation on somebody who joined mid-month. */
    public static function memberEligible(?string $joinedAt, ?string $leftAt, string $first): bool
    {
        return ($joinedAt === null || substr($joinedAt, 0, 10) <= $first)
            && ($leftAt === null || substr($leftAt, 0, 10) >= $first);
    }

    public static function allowedMonth(string $month, string $today, string $cycleStart, string $cycleEnd): bool
    {
        [$first, $last] = self::bounds($month);
        $current = substr($today, 0, 7).'-01';
        // Only current/future FULL months. Past dates and partial final-cycle months require human review.
        return $first >= $current && $first >= substr($cycleStart, 0, 10)
            && $last <= substr($cycleEnd, 0, 10);
    }
}
