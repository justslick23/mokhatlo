<?php

namespace App\Services\MokhatloMail;

use App\Support\Money;
use Illuminate\Support\Facades\DB;

/** Posted accounting entries for the society; not additional cash flow. */
final class MonthlyReportJournal
{
    public function forPeriod(int $societyId, int $cycleId, string $start, string $end): array
    {
        // Some legacy opening/clearing entries may be society-wide or have
        // a null cycle id. We disclose all posted society journals, labelled
        // with their original cycle if present, rather than silently exclude.
        $txs = DB::table('ledger_transactions')
            ->where('society_id', $societyId)
            ->whereBetween('posting_date', [$start, $end])
            ->orderBy('posting_date')->orderBy('id')
            ->get(['id','cycle_id','posting_date','source_type']);
        if ($txs->isEmpty()) return [];
        $lines = DB::table('ledger_lines as ll')
            ->join('ledger_accounts as a', 'a.id', '=', 'll.ledger_account_id')
            ->where('a.society_id', $societyId)
            ->whereIn('ll.ledger_transaction_id', $txs->pluck('id')->all())
            ->orderBy('ll.id')
            ->get(['ll.ledger_transaction_id','a.code','a.name','ll.debit_amount','ll.credit_amount'])
            ->groupBy('ledger_transaction_id');
        $result = [];
        foreach ($txs as $t) {
            $accountLines = [];
            $debits = 0;
            $credits = 0;
            foreach ($lines->get($t->id, collect()) as $line) {
                $d = Money::toCents((string) $line->debit_amount);
                $c = Money::toCents((string) $line->credit_amount);
                $debits += $d;
                $credits += $c;
                $accountLines[] = [
                    'account' => (string) ($line->name ?: $line->code),
                    'code' => (string) $line->code,
                    'debit_cents' => $d, 'credit_cents' => $c,
                ];
            }
            $result[] = [
                'id' => (int) $t->id, 'date' => substr((string) $t->posting_date, 0, 10),
                'cycle_id' => $t->cycle_id === null ? null : (int) $t->cycle_id,
                'source_type' => (string) $t->source_type,
                'debit_cents' => $debits, 'credit_cents' => $credits,
                'balanced' => $debits === $credits,
                'lines' => $accountLines,
            ];
        }
        return $result;
    }
}
