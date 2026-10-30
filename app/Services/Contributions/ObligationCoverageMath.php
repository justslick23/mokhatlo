<?php

namespace App\Services\Contributions;

use InvalidArgumentException;

/** Pure, integer-cent arithmetic shared by ordinary and historical compliance. */
final class ObligationCoverageMath
{
    public static function calculate(int $requiredCents, int $directCents, int $historicalCents): array
    {
        if ($requiredCents < 0 || $directCents < 0 || $historicalCents < 0) {
            throw new InvalidArgumentException('Obligation and receipt amounts cannot be negative.');
        }

        $paid = $directCents + $historicalCents;
        $outstanding = max(0, $requiredCents - $paid);
        $excess = max(0, $paid - $requiredCents);

        return [
            'required_cents' => $requiredCents,
            'direct_paid_cents' => $directCents,
            'historical_paid_cents' => $historicalCents,
            'paid_cents' => $paid,
            'outstanding_cents' => $outstanding,
            'excess_cents' => $excess,
            'fully_paid' => $paid >= $requiredCents,
        ];
    }
}
