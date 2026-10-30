<?php

namespace App\Services\Legacy;

final class LegacyTransactionType
{
    public static function classify(?string $type): string
    {
        return match (strtolower(trim((string) $type))) {
            'contribution' => 'contribution_received',

            'loan_issued',
            'loan_disbursement' => 'loan_disbursement',

            'loan_repayment' => 'loan_repayment_received',

            'interest',
            'loan_interest' => 'interest_component',

            'penalty' => 'penalty_unverified',

            'year_end_payout' => 'year_end_payout_unverified',

            default => 'unknown',
        };
    }

    public static function needsReview(string $kind): bool
    {
        return in_array($kind, [
            'interest_component',
            'penalty_unverified',
            'year_end_payout_unverified',
            'unknown',
        ], true);
    }

    public static function isIndependentCashMovement(
        string $kind
    ): bool {
        return in_array($kind, [
            'contribution_received',
            'loan_disbursement',
            'loan_repayment_received',
        ], true);
    }
}
