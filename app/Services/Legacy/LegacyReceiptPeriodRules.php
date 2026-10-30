<?php

namespace App\Services\Legacy;

use InvalidArgumentException;

final class LegacyReceiptPeriodRules
{
    public static function monthStart(string $month): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/D', $month, $matches)) {
            throw new InvalidArgumentException('Month must use YYYY-MM.');
        }

        if (! checkdate((int) $matches[2], 1, (int) $matches[1])) {
            throw new InvalidArgumentException('Invalid month.');
        }

        return $month . '-01';
    }

    public static function assertWithinCycle(string $period, string $start, string $end): void
    {
        $endOfPeriod = date('Y-m-t', strtotime($period));
        if ($endOfPeriod < substr($start, 0, 10) || $period > substr($end, 0, 10)) {
            throw new InvalidArgumentException('Selected month is outside the historical cycle.');
        }
    }

    public static function available(int $receiptCents, int $alreadyAttributed): int
    {
        if ($receiptCents <= 0 || $alreadyAttributed < 0 || $alreadyAttributed > $receiptCents) {
            throw new InvalidArgumentException('Existing period attribution totals are invalid.');
        }
        return $receiptCents - $alreadyAttributed;
    }

    public static function assertMayAttribute(int $receiptCents, int $alreadyAttributed, int $newCents): void
    {
        $remaining = self::available($receiptCents, $alreadyAttributed);
        if ($newCents <= 0 || $newCents > $remaining) {
            throw new InvalidArgumentException('Allocation must be positive and cannot exceed the remaining receipt amount.');
        }
    }
}
