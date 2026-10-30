<?php

namespace App\Support;

use InvalidArgumentException;

final class Percentage
{
    private const DENOMINATOR = 10_000_000;

    /**
     * Apply a percentage to an integer-cent amount.
     *
     * Examples:
     * 5.00000% of 20000 cents = 1000 cents.
     * 2.50000% of 10000 cents = 250 cents.
     *
     * Rounds half up to the nearest cent.
     */
    public static function applyToCents(
        int $baseCents,
        string $rate
    ): int {
        if ($baseCents < 0) {
            throw new InvalidArgumentException(
                'Percentage base cannot be negative.'
            );
        }

        $rate = trim($rate);

        if (! preg_match(
            '/^(?:100(?:\.0{1,5})?|[0-9]{1,2}(?:\.[0-9]{1,5})?)$/',
            $rate
        )) {
            throw new InvalidArgumentException(
                'Percentage must be between 0 and 100.'
            );
        }

        [$whole, $fraction] = array_pad(
            explode('.', $rate, 2),
            2,
            '0'
        );

        $fraction = str_pad(
            $fraction,
            5,
            '0',
            STR_PAD_RIGHT
        );

        $rateUnits = ((int) $whole * 100_000)
            + (int) $fraction;

        // Split the operation to avoid large intermediate
        // multiplications that could overflow a 64-bit integer.
        $quotient = intdiv($baseCents, self::DENOMINATOR);
        $remainder = $baseCents % self::DENOMINATOR;

        $wholeResult = $quotient * $rateUnits;

        $fractionResult = intdiv(
            ($remainder * $rateUnits)
                + intdiv(self::DENOMINATOR, 2),
            self::DENOMINATOR
        );

        return $wholeResult + $fractionResult;
    }
}
