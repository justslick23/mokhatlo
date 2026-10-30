<?php

namespace App\Services\MokhatloMail;

use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Cycle-scoped financial event feed. Only the original financial transaction
 * records are cash movements. Historic allocation links are never emitted as
 * duplicate receipts. Interest credit and waivers are marked noncash.
 */
final class MonthlyReportTransactions
{
    /** @return array<int,array<string,mixed>> */
    public function forPeriod(int $societyId, int $cycleId, string $start, string $end): array
    {
        $rows = [];
        $add = static function (string $date, int $memberId, string $category,
            string $detail, string $direction, string $amount,
            string $source, int $sourceId, ?string $reference = null,
            int $interestCents = 0) use (&$rows): void {
            $rows[] = [
                'date' => substr($date, 0, 10),
                'membership_id' => $memberId,
                'category' => $category,
                'detail' => $detail,
                'direction' => $direction, // in, out, or noncash
                'amount_cents' => Money::toCents($amount),
                'source' => $source,
                'source_id' => $sourceId,
                'reference' => $reference,
                'interest_component_cents' => $interestCents,
            ];
        };

        // Cash into savings: each contribution_payments row once, irrespective
        // of how many historical months that payment covered.
        foreach (DB::table('contribution_payments')
            ->where('society_id', $societyId)->where('cycle_id', $cycleId)
            ->whereBetween('paid_on', [$start, $end])
            ->get(['id','society_membership_id','amount','paid_on','payment_reference']) as $p) {
            $add($p->paid_on, (int) $p->society_membership_id, 'Savings',
                'Contribution received', 'in', (string) $p->amount,
                'contribution', (int) $p->id, $p->payment_reference);
        }

        foreach (DB::table('loan_disbursements as d')
            ->join('loans as l', 'l.id', '=', 'd.loan_id')
            ->where('d.society_id', $societyId)->where('l.society_id', $societyId)
            ->where('l.cycle_id', $cycleId)
            ->whereBetween('d.disbursed_on', [$start, $end])
            ->get(['d.id','l.society_membership_id','l.loan_number','d.amount','d.disbursed_on','d.payment_reference']) as $p) {
            $add($p->disbursed_on, (int) $p->society_membership_id, 'Loan disbursement',
                'Loan ' . $p->loan_number . ' issued', 'out', (string) $p->amount,
                'disbursement', (int) $p->id, $p->payment_reference);
        }

        $repayments = DB::table('loan_repayments as r')
            ->join('loans as l', 'l.id', '=', 'r.loan_id')
            ->where('r.society_id', $societyId)->where('l.society_id', $societyId)
            ->where('l.cycle_id', $cycleId)
            ->whereBetween('r.paid_on', [$start, $end])
            ->get(['r.id','l.society_membership_id','l.loan_number','r.amount','r.paid_on','r.payment_reference']);
        $interests = $repayments->isEmpty() ? collect() : DB::table('loan_repayment_allocations')
            ->whereIn('loan_repayment_id', $repayments->pluck('id')->all())
            ->get(['loan_repayment_id', 'interest_amount'])
            ->groupBy('loan_repayment_id');
        foreach ($repayments as $p) {
            $interest = $interests->get($p->id, collect())->sum(
                fn ($allocation) => Money::toCents((string) $allocation->interest_amount)
            );
            $detail = 'Loan ' . $p->loan_number . ' repaid';
            if ($interest > 0) {
                $detail .= ' (includes M ' . number_format($interest / 100, 2) . ' interest)';
            }
            $add($p->paid_on, (int) $p->society_membership_id, 'Loan repayment',
                $detail, 'in', (string) $p->amount, 'repayment', (int) $p->id,
                $p->payment_reference, $interest);
        }

        foreach (DB::table('contribution_penalty_payments as p')
            ->join('contribution_penalties as cp', 'cp.id', '=', 'p.contribution_penalty_id')
            ->join('contribution_obligations as o', 'o.id', '=', 'cp.contribution_obligation_id')
            ->where('p.society_id', $societyId)->where('cp.society_id', $societyId)
            ->where('o.society_id', $societyId)->where('cp.cycle_id', $cycleId)
            ->whereBetween('p.paid_on', [$start, $end])
            ->get(['p.id','o.society_membership_id','o.period_start','p.amount','p.paid_on','p.payment_reference']) as $p) {
            $add($p->paid_on, (int) $p->society_membership_id, 'Contribution penalty',
                'Penalty received for ' . substr((string) $p->period_start, 0, 7), 'in',
                (string) $p->amount, 'contribution_penalty_payment', (int) $p->id,
                $p->payment_reference);
        }
        foreach (DB::table('loan_penalty_payments as p')
            ->join('loan_penalties as lp', 'lp.id', '=', 'p.loan_penalty_id')
            ->join('loans as l', 'l.id', '=', 'lp.loan_id')
            ->where('p.society_id', $societyId)->where('lp.society_id', $societyId)
            ->where('l.society_id', $societyId)->where('l.cycle_id', $cycleId)
            ->whereBetween('p.paid_on', [$start, $end])
            ->get(['p.id','l.society_membership_id','l.loan_number','p.amount','p.paid_on','p.payment_reference']) as $p) {
            $add($p->paid_on, (int) $p->society_membership_id, 'Loan penalty',
                'Penalty received on loan ' . $p->loan_number, 'in', (string) $p->amount,
                'loan_penalty_payment', (int) $p->id, $p->payment_reference);
        }

        // Refund entitlement: a member-owned credit, not a second repayment.
        foreach (DB::table('loan_interest_credits as c')
            ->join('loans as l', 'l.id', '=', 'c.loan_id')
            ->join('loan_repayments as r', 'r.id', '=', 'c.loan_repayment_id')
            ->where('c.society_id', $societyId)->where('l.society_id', $societyId)
            ->where('r.society_id', $societyId)->where('l.cycle_id', $cycleId)
            ->whereBetween('r.paid_on', [$start, $end])
            ->get(['c.id','c.beneficiary_membership_id','l.loan_number','c.amount','r.paid_on']) as $p) {
            $add($p->paid_on, (int) $p->beneficiary_membership_id,
                'Borrower interest credit', 'Refundable interest earned on ' . $p->loan_number,
                'noncash', (string) $p->amount, 'interest_credit', (int) $p->id);
        }

        // Waivers affect debt but are never receipts. This may be empty on many months.
        foreach ([
            ['table' => 'contribution_penalty_waivers', 'fk' => 'contribution_penalty_id',
                'join' => 'contribution_penalties as pen', 'memberJoin' => 'contribution_obligations as o',
                'memberOn' => 'o.id', 'scopeColumn' => 'pen.cycle_id', 'memberColumn' => 'o.society_membership_id',
                'extraOn' => 'pen.contribution_obligation_id', 'category' => 'Contribution penalty waiver'],
            ['table' => 'loan_penalty_waivers', 'fk' => 'loan_penalty_id',
                'join' => 'loan_penalties as pen', 'memberJoin' => 'loans as o',
                'memberOn' => 'o.id', 'scopeColumn' => 'o.cycle_id', 'memberColumn' => 'o.society_membership_id',
                'extraOn' => 'pen.loan_id', 'category' => 'Loan penalty waiver'],
        ] as $kind) {
            $w = DB::table($kind['table'] . ' as w')
                ->join($kind['join'], 'pen.id', '=', 'w.' . $kind['fk'])
                ->join($kind['memberJoin'], $kind['memberOn'], '=', $kind['extraOn'])
                ->where('w.society_id', $societyId)
                ->where('pen.society_id', $societyId)
                ->where('o.society_id', $societyId)
                ->where($kind['scopeColumn'], $cycleId)
                ->whereDate('w.created_at', '>=', $start)->whereDate('w.created_at', '<=', $end)
                ->get(['w.id', 'w.amount', 'w.created_at', $kind['memberColumn'] . ' as member_id']);
            foreach ($w as $p) {
                $add($p->created_at, (int) $p->member_id, $kind['category'],
                    'Penalty waiver (non-cash adjustment)', 'noncash', (string) $p->amount,
                    $kind['table'], (int) $p->id);
            }
        }

        usort($rows, static fn (array $a, array $b) => [$a['date'], $a['source'], $a['source_id']]
            <=> [$b['date'], $b['source'], $b['source_id']]);
        return $rows;
    }
}
