<?php

namespace App\Services\MokhatloMail;

use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only bank history for member-facing reports.
 *
 * On the original legacy import date, bank/cash transactions were posted to
 * LEGACY_IMPORT_CLEARING. When the society later approved one bank-only
 * reconciliation, the full clearing balance was reclassified to BANK_ACCOUNT.
 * For earlier dates we can reverse the intervening imported transactions from
 * that bank anchor, but only if all evidence and source-type guards pass.
 *
 * A reconstructed figure is never a separately verified historic statement.
 */
final class HistoricalBankBalanceForReport
{
    public static function reverseMovements(int $anchorCents, int $netMovementsAfterDateCents): int
    {
        return $anchorCents - $netMovementsAfterDateCents;
    }

    /** @param array<string,int> $raw Financial balances from ledger(). */
    public function resolve(int $societyId, string $endDate, array $raw): array
    {
        $recorded = (int) ($raw['BANK_ACCOUNT'] ?? 0);
        $default = [
            'amount_cents' => $recorded,
            'status' => 'recorded',
            'label' => 'Bank balance recorded at month-end',
            'note' => 'Bank account entries posted by the end of this month.',
            'anchor_date' => null,
        ];

        if (! Schema::hasTable('legacy_clearing_reconciliations')) {
            return $default;
        }

        $anchors = DB::table('legacy_clearing_reconciliations as r')
            ->join('ledger_transactions as t', 't.id', '=', 'r.ledger_transaction_id')
            ->join('legacy_financial_import_runs as run', 'run.id', '=', 'r.import_run_id')
            ->where('r.society_id', $societyId)
            ->where('run.society_id', $societyId)
            ->where('t.society_id', $societyId)
            ->where('t.source_type', 'legacy_clearing_reconciliation')
            ->whereColumn('t.source_id', 'r.id')
            ->where('r.status', 'posted')
            ->get([
                'r.id', 'r.ledger_transaction_id', 'r.expected_cents',
                'r.cash_cents', 'r.bank_cents', 'r.mobile_cents',
                'r.approved_by', 'r.evidence_reference', 't.posting_date',
            ]);

        // Do not conflate historical funds from different imports or banks.
        if ($anchors->count() !== 1) {
            return $default;
        }
        $anchor = $anchors->first();
        $anchorDate = substr((string) $anchor->posting_date, 0, 10);
        if ($endDate >= $anchorDate) {
            return $default; // Dated, native bank ledger now has the actual posting.
        }
        if (
            (int) $anchor->expected_cents <= 0
            || (int) $anchor->bank_cents !== (int) $anchor->expected_cents
            || (int) $anchor->cash_cents !== 0
            || (int) $anchor->mobile_cents !== 0
            || ! $anchor->approved_by
            || trim((string) $anchor->evidence_reference) === ''
        ) {
            return $default;
        }

        // Mixed cash/bank posting before reconciliation would require separate
        // account-by-account evidence; don't call legacy clearing 'bank' then.
        if ($recorded !== 0
            || (int) ($raw['CASH_ON_HAND'] ?? 0) !== 0
            || (int) ($raw['MOBILE_MONEY'] ?? 0) !== 0) {
            return $default;
        }

        $bankLine = DB::table('ledger_lines as l')
            ->join('ledger_accounts as a', 'a.id', '=', 'l.ledger_account_id')
            ->where('l.ledger_transaction_id', $anchor->ledger_transaction_id)
            ->where('a.society_id', $societyId)
            ->where('a.code', 'BANK_ACCOUNT')
            ->get(['l.debit_amount', 'l.credit_amount'])
            ->sum(fn ($line) => Money::toCents((string) $line->debit_amount)
                - Money::toCents((string) $line->credit_amount));
        if ($bankLine !== (int) $anchor->bank_cents) {
            return $default;
        }

        // Only the three known, original import cash-movement source types
        // are admissible for the historical reconstruction.
        $clearingLines = DB::table('ledger_lines as l')
            ->join('ledger_accounts as a', 'a.id', '=', 'l.ledger_account_id')
            ->join('ledger_transactions as t', 't.id', '=', 'l.ledger_transaction_id')
            ->where('a.society_id', $societyId)
            ->where('a.code', 'LEGACY_IMPORT_CLEARING')
            ->where('t.society_id', $societyId)
            ->whereDate('t.posting_date', '<=', $anchorDate)
            ->get(['t.posting_date', 't.source_type', 't.source_id',
                'l.debit_amount', 'l.credit_amount']);

        $allowed = ['contribution_payment', 'loan_disbursement', 'loan_repayment'];
        $netUntilDate = 0;
        $netAfterDate = 0;
        $anchorNet = 0;
        foreach ($clearingLines as $line) {
            $isAnchorLine = $line->source_type === 'legacy_clearing_reconciliation'
                && (int) $line->source_id === (int) $anchor->id;
            if (! $isAnchorLine && ! in_array($line->source_type, $allowed, true)) {
                return $default;
            }
            $net = Money::toCents((string) $line->debit_amount)
                - Money::toCents((string) $line->credit_amount);
            if ($isAnchorLine) {
                $anchorNet += $net;
                continue;
            }
            $date = substr((string) $line->posting_date, 0, 10);
            if ($date <= $endDate) $netUntilDate += $net;
            if ($date > $endDate && $date <= $anchorDate) $netAfterDate += $net;
        }
        $bank = self::reverseMovements((int) $anchor->bank_cents, $netAfterDate);
        // Both reconstruction methods must agree; otherwise fail closed.
        if ($bank !== $netUntilDate
            || $anchorNet !== -(int) $anchor->expected_cents
            || $bank < 0
            || $bank !== (int) ($raw['LEGACY_IMPORT_CLEARING'] ?? 0)) {
            return $default;
        }

        return [
            'amount_cents' => $bank,
            'status' => 'reconstructed',
            'label' => 'Bank balance calculated from historical transactions',
            'note' => 'Estimated from recorded savings, loan payments and loans given out, '
                . 'working backwards from the bank balance reconciled on ' . $anchorDate
                . '. It may differ from an old bank statement if fees or transfers were not recorded.',
            'anchor_date' => $anchorDate,
        ];
    }
}
