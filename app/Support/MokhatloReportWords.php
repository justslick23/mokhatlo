<?php

namespace App\Support;

/**
 * Friendly wording for society members. Only the display text changes:
 * source types, amounts, and ledger postings are never altered.
 */
final class MokhatloReportWords
{
    public static function transactionType(array $row): string
    {
        return match ((string) ($row['source'] ?? '')) {
            'contribution' => 'Savings paid',
            'disbursement' => 'Loan given',
            'repayment' => 'Loan payment',
            'contribution_penalty_payment' => 'Savings penalty paid',
            'loan_penalty_payment' => 'Loan penalty paid',
            'interest_credit' => 'Interest earned',
            'contribution_penalty_waivers' => 'Savings penalty cancelled',
            'loan_penalty_waivers' => 'Loan penalty cancelled',
            default => (string) ($row['category'] ?? 'Other activity'),
        };
    }

    public static function description(array $row): string
    {
        $detail = (string) ($row['detail'] ?? $row['description'] ?? '');

        return match ((string) ($row['source'] ?? '')) {
            'contribution' => 'Money added to savings',
            'disbursement' => str_replace(' issued', ' given to member', $detail),
            'repayment' => str_replace(' repaid', ' - payment received', $detail),
            'contribution_penalty_payment' => str_replace('Penalty received for', 'Penalty paid for', $detail),
            'loan_penalty_payment' => str_replace('Penalty received on', 'Penalty paid for', $detail),
            'interest_credit' => str_replace('Refundable interest earned on', 'Interest earned from', $detail) . ' (not another payment)',
            'contribution_penalty_waivers', 'loan_penalty_waivers' => 'Penalty cancelled; no money was paid',
            default => $detail,
        };
    }

    public static function memberAction(array $row): string
    {
        return match ((string) ($row['source'] ?? '')) {
            'contribution', 'repayment', 'contribution_penalty_payment', 'loan_penalty_payment' => 'You paid',
            'disbursement' => 'You received',
            'interest_credit' => 'You earned',
            'contribution_penalty_waivers', 'loan_penalty_waivers' => 'Cancelled',
            default => ($row['direction'] ?? '') === 'noncash' ? 'For your records' : 'Recorded',
        };
    }

    public static function societyAction(array $row): string
    {
        return match ((string) ($row['source'] ?? '')) {
            'contribution', 'repayment', 'contribution_penalty_payment', 'loan_penalty_payment' => 'Money received',
            'disbursement' => 'Money paid out',
            'interest_credit' => 'Interest earned',
            'contribution_penalty_waivers', 'loan_penalty_waivers' => 'Penalty cancelled',
            default => ($row['direction'] ?? '') === 'noncash' ? 'For records only' : 'Recorded',
        };
    }

    public static function holding(string $code): string
    {
        return match ($code) {
            'CASH_ON_HAND' => 'Cash held',
            'BANK_ACCOUNT' => 'Money in the bank',
            'MOBILE_MONEY' => 'Money in mobile wallets',
            'liquid_assets' => 'Total money held',
            'LOAN_PRINCIPAL_RECEIVABLE' => 'Loan money members still need to repay (before interest)',
            'MEMBER_SAVINGS' => 'Total savings belonging to members',
            'MEMBER_INTEREST_PAYABLE' => 'Interest earned for members',
            'LEGACY_IMPORT_CLEARING' => 'Older money still being checked',
            default => $code,
        };
    }
}
