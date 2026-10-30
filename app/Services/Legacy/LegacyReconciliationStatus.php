<?php

namespace App\Services\Legacy;

use App\Models\Society;
use App\Support\Money;
use App\Services\Contributions\ContributionObligationCoverage;
use App\Services\Reports\SocietyFinancialReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Independent safety gates; no financial postings. */
final class LegacyReconciliationStatus
{
    public function __construct(
        private readonly ContributionObligationCoverage $coverage,
        private readonly SocietyFinancialReport $financial
    ) {}

    public function forSociety(Society $society): array
    {
        if (! Schema::hasTable('legacy_financial_import_runs')) {
            return ['imported' => false, 'blockers' => []];
        }
        $run = DB::table('legacy_financial_import_runs')
            ->where('society_id', $society->id)->orderByDesc('id')->first();
        if (! $run) {
            return ['imported' => false, 'blockers' => []];
        }
        $cycleId = (int) $run->cycle_id;
        $coverage = $this->coverage->forCycle((int) $society->id, $cycleId);
        $totals = $coverage['totals'];
        $report = $this->financial->build($society, $cycleId);
        $paymentsTotal = (int) $report['contributions_received'];
        $linksComplete = $totals['obligations'] > 0
            && $totals['outstanding_cents'] === 0
            && $totals['paid_cents'] === $paymentsTotal;
        $reportVerified = $report['historical_cycle']
            && $report['contributions_required'] === $totals['required_cents']
            && $report['contributions_outstanding'] === $totals['outstanding_cents']
            && $report['contributions_received'] === $paymentsTotal;

        $pendingPenalty = DB::table('legacy_financial_review_items')
            ->where('legacy_financial_import_run_id', $run->id)
            ->where('resolution_status', 'pending')->count();
        // Documented collected penalties remain NOT posted as cash/income. Any
        // such decision must be reconciled through a separate verified journal.
        $collectedNeedsPosting = DB::table('legacy_penalty_review_actions as a')
            ->join('legacy_financial_review_items as r', 'r.id', '=', 'a.review_item_id')
            ->where('r.legacy_financial_import_run_id', $run->id)
            ->where('r.resolution_status', 'resolved')
            ->where('a.decision', 'collected_documented')
            ->whereRaw('a.id = (SELECT MAX(a2.id) FROM legacy_penalty_review_actions a2 WHERE a2.review_item_id = a.review_item_id)')
            ->count();
        $documentedOutstanding = DB::table('legacy_penalty_review_actions as a')
            ->join('legacy_financial_review_items as r', 'r.id', '=', 'a.review_item_id')
            ->where('r.legacy_financial_import_run_id', $run->id)
            ->where('r.resolution_status', 'resolved')
            ->where('a.decision', 'outstanding_documented')
            ->whereRaw('a.id = (SELECT MAX(a2.id) FROM legacy_penalty_review_actions a2 WHERE a2.review_item_id = a.review_item_id)')
            ->select('r.id as review_id', 'r.loan_id', 'r.amount_cents')->get();
        // A native penalty must exist, belong to this loan/society, and still
        // match its reviewed assessed amount. Paid amounts can later decline.
        $verifiedOutstandingPenalty = $documentedOutstanding->filter(function ($row) use ($society) {
            if (! Schema::hasTable('legacy_imported_loan_penalties')) return true;
            $link = DB::table('legacy_imported_loan_penalties')
                ->where('review_item_id', $row->review_id)->first();
            if (! $link || (int) $link->loan_id !== (int) $row->loan_id
                || (int) $link->amount_cents !== (int) $row->amount_cents
                || (int) $link->society_id !== (int) $society->id) return true;
            $native = DB::table('loan_penalties')
                ->where('id', $link->loan_penalty_id)
                ->where('society_id', $society->id)
                ->where('loan_id', $row->loan_id)->first();
            return ! $native || Money::toCents((string) $native->assessed_amount) !== (int) $row->amount_cents;
        })->count();
        $invalidLinkedReviews = Schema::hasTable('legacy_imported_loan_penalties')
            ? DB::table('legacy_imported_loan_penalties as m')
                ->join('legacy_financial_review_items as r', 'r.id', '=', 'm.review_item_id')
                ->where('m.society_id', $society->id)
                ->where('r.resolution_status', '!=', 'resolved')->count()
            : 0;


        $clearing = DB::table('legacy_clearing_reconciliations')
            ->where('import_run_id', $run->id)->first();
        $postedClearing = $clearing && $clearing->status === 'posted'
            && $clearing->ledger_transaction_id !== null
            && DB::table('ledger_transactions')->where('id', $clearing->ledger_transaction_id)
                ->where('society_id', $society->id)
                ->where('source_type', 'legacy_clearing_reconciliation')
                ->where('source_id', $clearing->id)->exists();

        $loanOpen = (int) $report['loan_principal_outstanding']
            + (int) $report['loan_interest_outstanding'];
        $blockers = [];
        if (! $linksComplete) $blockers[] = 'Recorded historic obligations are not fully linked to original savings receipts.';
        if (! $reportVerified) $blockers[] = 'Ordinary financial report is not reconciled to historical contribution coverage.';
        if ($pendingPenalty > 0) $blockers[] = "{$pendingPenalty} historical penalty evidence decisions remain pending.";
        if ($collectedNeedsPosting > 0) $blockers[] = "{$collectedNeedsPosting} documented collected penalties still require audited financial posting.";
        if ($invalidLinkedReviews > 0) $blockers[] = "{$invalidLinkedReviews} linked historical penalty reviews changed status; manual reversal/investigation required.";
        if ($verifiedOutstandingPenalty > 0) $blockers[] = "{$verifiedOutstandingPenalty} historical outstanding penalty balances still require loan-penalty subledger review.";
        if (! $postedClearing) $blockers[] = 'Legacy clearing is not independently approved and posted to verified asset accounts.';
        if ($loanOpen > 0) $blockers[] = 'Outstanding contractual loan balance: M '.number_format($loanOpen / 100, 2).'.';
        $cycle = $society->cycles()->whereKey($cycleId)->first();
        if ($cycle && now($society->timezone ?: 'Africa/Maseru')->toDateString() < substr((string) $cycle->end_date, 0, 10)) {
            $blockers[] = 'Historical savings cycle has not reached its end date.';
        }

        return [
            'imported' => true, 'run' => $run, 'cycle_id' => $cycleId,
            'compliance' => $coverage, 'report_verified' => $reportVerified,
            'obligations_linked' => $linksComplete,
            'pending_penalties' => $pendingPenalty,
            'collected_pending_accounting' => $collectedNeedsPosting,
            'outstanding_penalties_pending_subledger' => $verifiedOutstandingPenalty,
            'invalid_linked_penalty_reviews' => $invalidLinkedReviews,
            'clearing' => $clearing, 'clearing_posted' => (bool) $postedClearing,
            'contractual_outstanding_cents' => $loanOpen,
            'blockers' => $blockers,
        ];
    }
}
