<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function toCents(string $amount): int
    {
        $amount = trim($amount);

        if (! preg_match(
            '/^\d{1,16}(\.\d{1,2})?$/',
            $amount
        )) {
            throw new InvalidArgumentException(
                'Invalid monetary amount: ' . $amount
            );
        }

        [$whole, $fraction] = array_pad(
            explode('.', $amount, 2),
            2,
            '0'
        );

        $fraction = str_pad(
            $fraction,
            2,
            '0',
            STR_PAD_RIGHT
        );

        return ((int) $whole * 100) + (int) $fraction;
    }

    public static function fromCents(int $cents): string
    {
        $negative = $cents < 0;

        $cents = abs($cents);

        return ($negative ? '-' : '')
            . intdiv($cents, 100)
            . '.'
            . str_pad(
                (string) ($cents % 100),
                2,
                '0',
                STR_PAD_LEFT
            );
    }
}
