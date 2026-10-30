<?php

namespace App\Services\Payouts;

use DomainException;

final class AllocateMemberPayoutPayment
{
    /**
     * Allocate in order:
     * savings, interest refund, penalty share.
     *
     * All values are integer cents.
     *
     * @param array<string,int> $entitlement
     * @param array<string,int> $alreadyPaid
     * @return array<string,int>
     */
    public function execute(
        array $entitlement,
        array $alreadyPaid,
        int $amountCents
    ): array {
        if ($amountCents <= 0) {
            throw new DomainException(
                'Payout must be greater than zero.'
            );
        }

        $remaining = [];

        foreach (['savings', 'interest', 'penalty'] as $key) {
            $total = $entitlement[$key] ?? 0;
            $paid = $alreadyPaid[$key] ?? 0;

            if (
                $total < 0
                || $paid < 0
                || $paid > $total
            ) {
                throw new DomainException(
                    "Invalid {$key} payout balance."
                );
            }

            $remaining[$key] = $total - $paid;
        }

        if ($amountCents > array_sum($remaining)) {
            throw new DomainException(
                'Payment exceeds the remaining payout entitlement.'
            );
        }

        $left = $amountCents;

        $result = [
            'savings' => 0,
            'interest' => 0,
            'penalty' => 0,
        ];

        foreach (array_keys($result) as $key) {
            $result[$key] = min(
                $left,
                $remaining[$key]
            );

            $left -= $result[$key];
        }

        if ($left !== 0) {
            throw new DomainException(
                'Payout allocation failed to balance.'
            );
        }

        return $result;
    }
}
