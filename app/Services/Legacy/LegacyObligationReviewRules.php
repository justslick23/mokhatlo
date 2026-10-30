<?php

namespace App\Services\Legacy;

use InvalidArgumentException;

final class LegacyObligationReviewRules
{
    public static function assertObligationDate(string $period, string $due, string $cycleStart, string $cycleEnd): void
    {
        if (! preg_match('/^\d{4}-\d{2}-01$/D', $period)) {
            throw new InvalidArgumentException('The reviewed month must be a valid month start.');
        }
        if (! self::validDate($period) || ! self::validDate($due)) {
            throw new InvalidArgumentException('Invalid obligation period or due date.');
        }
        if (substr($period, 0, 7) !== substr($due, 0, 7)) {
            throw new InvalidArgumentException('Due date must be within the reviewed obligation month.');
        }
        if ($period < substr($cycleStart, 0, 10) || $due > substr($cycleEnd, 0, 10)) {
            throw new InvalidArgumentException('Obligation dates must be inside the cycle.');
        }
    }

    public static function assertAmount(int $attributionCents, int $receiptCents, int $alreadyLinkedCents): void
    {
        if ($attributionCents <= 0 || $receiptCents <= 0 || $alreadyLinkedCents < 0 ||
            $alreadyLinkedCents + $attributionCents > $receiptCents) {
            throw new InvalidArgumentException('Link would over-allocate the historical receipt.');
        }
    }

    private static function validDate(string $date): bool
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $m)) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
