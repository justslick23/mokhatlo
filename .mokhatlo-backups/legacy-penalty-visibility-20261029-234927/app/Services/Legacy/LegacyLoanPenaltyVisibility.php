<?php

namespace App\Services\Legacy;

use App\Models\Loan;
use App\Models\Society;
use App\Services\Loans\LoanBalances;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only view of evidence-backed, historic loan penalty balances that
 * have NOT been integrated into the native LoanPenalty subledger.
 * They are NOT posted cash or duplicate contractual loan interest.
 */
final class LegacyLoanPenaltyVisibility
{
    public function __construct(private readonly LoanBalances $balances) {}

    public function forLoan(Loan $loan): array
    {
        $base = $this->balances->summary($loan);
        $contractual = (int) $base['principal_outstanding']
            + (int) $base['interest_outstanding'];
        $penalty = $this->documentedAmountForLoan((int) $loan->society_id, (int) $loan->id);

        return [
            'loan_id' => (int) $loan->id,
            'loan_number' => (string) $loan->loan_number,
            'contractual_cents' => $contractual,
            'documented_historical_penalty_cents' => $penalty,
            'combined_cents' => $contractual + $penalty,
            'subledger_pending' => $penalty > 0,
        ];
    }

    public function forCycle(Society $society, int $cycleId): array
    {
        $loans = Loan::query()
            ->where('society_id', $society->id)
            ->where('cycle_id', $cycleId)
            ->whereIn('status', ['disbursed', 'settled'])
            ->orderBy('id')
            ->get();

        $rows = [];
        $totalContractual = 0;
        $totalPenalty = 0;
        foreach ($loans as $loan) {
            $row = $this->forLoan($loan);
            $totalContractual += $row['contractual_cents'];
            $totalPenalty += $row['documented_historical_penalty_cents'];
            if ($row['documented_historical_penalty_cents'] > 0) {
                $rows[] = $row;
            }
        }

        return [
            'rows' => $rows,
            'contractual_cents' => $totalContractual,
            'documented_historical_penalties_cents' => $totalPenalty,
            'combined_cents' => $totalContractual + $totalPenalty,
            'note' => 'Historic reviewed penalties are displayed separately; they are not yet native loan-penalty subledger entries.',
        ];
    }

    private function documentedAmountForLoan(int $societyId, int $loanId): int
    {
        if (! Schema::hasTable('legacy_financial_review_items')
            || ! Schema::hasTable('legacy_penalty_review_actions')
            || ! Schema::hasTable('legacy_financial_import_runs')) {
            return 0;
        }

        $items = DB::table('legacy_financial_review_items as i')
            ->join('legacy_financial_import_runs as r', 'r.id', '=', 'i.legacy_financial_import_run_id')
            ->join('legacy_penalty_review_actions as a', function ($join) {
                $join->on('a.review_item_id', '=', 'i.id')
                    ->whereRaw('a.id = (SELECT MAX(a2.id) FROM legacy_penalty_review_actions a2 WHERE a2.review_item_id = i.id)');
            })
            ->where('r.society_id', $societyId)
            ->where('i.loan_id', $loanId)
            ->where('i.source_table', 'loans')
            ->where('i.reason', 'outstanding_penalty_unverified')
            ->where('i.resolution_status', 'resolved')
            ->where('a.decision', 'outstanding_documented')
            ->pluck('i.amount_cents');

        return $items->sum(fn ($cents) => max(0, (int) $cents));
    }
}
