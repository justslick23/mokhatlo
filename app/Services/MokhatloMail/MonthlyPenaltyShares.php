<?php

namespace App\Services\MokhatloMail;

use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Provisional, date-bounded penalty sharing for monthly reports.
 * Counts *payments* only, never assessments or waived/unpaid charges.
 * Does not post cash, change cycle policy, or approve payouts.
 */
final class MonthlyPenaltyShares
{
    /** Even allocation in whole cents; deterministic remainder belongs to lowest member IDs. */
    public static function splitEvenly(int $amountCents, array $memberIds): array
    {
        if ($amountCents < 0) {
            throw new \InvalidArgumentException('Penalty pool cannot be negative.');
        }
        $ids = array_values(array_unique(array_map('intval', $memberIds)));
        sort($ids, SORT_NUMERIC);
        if ($ids === []) {
            return [];
        }
        $each = intdiv($amountCents, count($ids));
        $remainder = $amountCents % count($ids);
        $shares = [];
        foreach ($ids as $i => $id) {
            $shares[$id] = $each + ($i < $remainder ? 1 : 0);
        }
        return $shares;
    }

    /** @return array{method:string, status:string, pool_cents:int, unposted_historical_cents:int, shares:array<int,int>, eligible_count:int} */
    public function forMonthEnd(int $societyId, int $cycleId, string $end): array
    {
        $cycle = DB::table('cycles')->where('id', $cycleId)
            ->where('society_id', $societyId)->first(['id', 'start_date']);
        if (! $cycle) {
            throw new \RuntimeException('Requested cycle does not belong to this society.');
        }
        $start = substr((string) $cycle->start_date, 0, 10);
        $contribution = DB::table('contribution_penalty_payments as p')
            ->join('contribution_penalties as pen', 'pen.id', '=', 'p.contribution_penalty_id')
            ->where('p.society_id', $societyId)->where('pen.society_id', $societyId)
            ->where('pen.cycle_id', $cycleId)
            ->whereBetween('p.paid_on', [$start, $end])->get(['p.id','p.amount']);
        $loan = DB::table('loan_penalty_payments as p')
            ->join('loan_penalties as pen', 'pen.id', '=', 'p.loan_penalty_id')
            ->join('loans as l', 'l.id', '=', 'pen.loan_id')
            ->where('p.society_id', $societyId)->where('pen.society_id', $societyId)
            ->where('l.society_id', $societyId)->where('l.cycle_id', $cycleId)
            ->whereBetween('p.paid_on', [$start, $end])->get(['p.id','p.amount']);
        $contributionCents = $contribution->sum(fn ($row) => Money::toCents((string) $row->amount));
        $loanCents = $loan->sum(fn ($row) => Money::toCents((string) $row->amount));
        $pool = $contributionCents + $loanCents;

        // A historical penalty payment can have documented collection without a
        // reconciled cash posting. It is in the provisional pool, but flagged.
        $unposted = 0;
        if (Schema::hasTable('legacy_imported_contribution_penalties')) {
            $unpostedRows = DB::table('legacy_imported_contribution_penalties as link')
                ->join('contribution_penalty_payments as p', 'p.id', '=', 'link.contribution_penalty_payment_id')
                ->join('contribution_penalties as pen', 'pen.id', '=', 'p.contribution_penalty_id')
                ->where('link.society_id', $societyId)
                ->where('p.society_id', $societyId)
                ->where('pen.cycle_id', $cycleId)
                ->whereNull('link.ledger_transaction_id')
                ->whereBetween('p.paid_on', [$start, $end])
                ->get(['p.amount']);
            $unposted = $unpostedRows->sum(fn ($row) => Money::toCents((string) $row->amount));
        }

        $method = Schema::hasColumn('cycle_policies', 'penalty_distribution_method')
            ? (string) (DB::table('cycle_policies')->where('society_id', $societyId)
                ->where('cycle_id', $cycleId)->value('penalty_distribution_method') ?? '')
            : '';

        // Current membership status cannot be projected backwards. Select members
        // present by the cutoff, plus imported members with earlier financial activity.
        $members = DB::table('society_memberships')
            ->where('society_id', $societyId)
            ->get(['id','joined_at','left_at','created_at','status']);
        $eligible = [];
        foreach ($members as $m) {
            $joined = $m->joined_at ? substr((string) $m->joined_at, 0, 10) : null;
            $left = $m->left_at ? substr((string) $m->left_at, 0, 10) : null;
            $historicalActivity = DB::table('contribution_payments')
                ->where('society_id', $societyId)->where('cycle_id', $cycleId)
                ->where('society_membership_id', $m->id)
                ->whereDate('paid_on', '<=', $end)->exists()
                || DB::table('loan_disbursements as d')
                    ->join('loans as l', 'l.id', '=', 'd.loan_id')
                    ->where('l.society_id', $societyId)
                    ->where('l.cycle_id', $cycleId)
                    ->where('l.society_membership_id', $m->id)
                    ->whereDate('d.disbursed_on', '<=', $end)->exists();
            $created = substr((string) $m->created_at, 0, 10);
            $present = (($joined !== null && $joined <= $end)
                    || ($joined === null && $created <= $end)
                    || $historicalActivity)
                && ($left === null || $left > $end);
            if ($present) {
                $eligible[] = (int) $m->id;
            }
        }

        // Only equal_all is implemented here. Never guess a different cycle's
        // distribution policy or pretend an unsupported policy means zero share.
        $shares = ($method === 'equal_all') ? self::splitEvenly($pool, $eligible) : [];
        $status = $pool === 0 ? 'none'
            : ($method !== 'equal_all' || $eligible === [] ? 'policy_review_needed'
                : ($unposted > 0 ? 'pending_cash_check' : 'provisional'));

        return [
            'method' => $method,
            'status' => $status,
            'pool_cents' => $pool,
            'unposted_historical_cents' => $unposted,
            'shares' => $shares,
            'eligible_count' => count($eligible),
        ];
    }
}
