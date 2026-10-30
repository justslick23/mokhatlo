<?php

namespace App\Services\Payouts;

use DomainException;
use OverflowException;

final class SplitPenaltyPool
{
    /**
     * Return membership ID => allocated cents.
     *
     * Uses integers throughout, with deterministic
     * distribution of rounding remainders.
     *
     * @param array<int, int> $weights
     * @return array<int, int>
     */
    public function execute(
        int $poolCents,
        string $method,
        array $weights
    ): array {
        if ($poolCents < 0) {
            throw new DomainException(
                'Penalty pool cannot be negative.'
            );
        }

        ksort($weights, SORT_NUMERIC);

        $ids = array_keys($weights);
        $result = array_fill_keys($ids, 0);

        if ($method === 'society_reserve') {
            return $result;
        }

        if (! in_array(
            $method,
            ['equal_all', 'proportional_savings'],
            true
        )) {
            throw new DomainException(
                'Unsupported penalty distribution method.'
            );
        }

        if ($poolCents === 0) {
            return $result;
        }

        if (count($ids) === 0) {
            throw new DomainException(
                'No eligible members for penalty distribution.'
            );
        }

        if ($method === 'equal_all') {
            $base = intdiv($poolCents, count($ids));
            $remainder = $poolCents % count($ids);

            foreach ($ids as $index => $id) {
                $result[$id] = $base
                    + ($index < $remainder ? 1 : 0);
            }

            return $result;
        }

        $totalWeight = array_sum($weights);

        if ($totalWeight <= 0) {
            throw new DomainException(
                'Cannot use proportional sharing without savings.'
            );
        }

        $allocated = 0;
        $remainders = [];

        foreach ($ids as $id) {
            $weight = $weights[$id];

            if ($weight < 0) {
                throw new DomainException(
                    'Savings weights cannot be negative.'
                );
            }

            if (
                $weight > 0
                && $poolCents > intdiv(PHP_INT_MAX, $weight)
            ) {
                throw new OverflowException(
                    'Distribution amount exceeds safe integer limits.'
                );
            }

            $numerator = $poolCents * $weight;

            $result[$id] = intdiv(
                $numerator,
                $totalWeight
            );

            $remainders[$id] = $numerator % $totalWeight;

            $allocated += $result[$id];
        }

        // Largest remainder method, stable on member ID.
        usort($ids, function ($a, $b) use ($remainders) {
            return ($remainders[$b] <=> $remainders[$a])
                ?: ($a <=> $b);
        });

        foreach ($ids as $id) {
            if ($allocated === $poolCents) {
                break;
            }

            $result[$id]++;
            $allocated++;
        }

        ksort($result, SORT_NUMERIC);

        return $result;
    }
}
