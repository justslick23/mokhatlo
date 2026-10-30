<?php

namespace App\Services\Reports;

use App\Models\ContributionPayment;
use App\Models\ContributionPenaltyPayment;
use App\Models\ContributionPenaltyWaiver;
use App\Models\LoanDisbursement;
use App\Models\LoanInterestCredit;
use App\Models\LoanPenaltyPayment;
use App\Models\LoanPenaltyWaiver;
use App\Models\LoanRepayment;
use App\Models\Society;
use App\Models\SocietyMembership;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MemberFinancialStatement
{
    public function build(Society $society, SocietyMembership $membership): Collection
    {
        abort_unless((int) $membership->society_id === (int) $society->id, 404);
        $rows = collect();
        $append = function (string $date, string $category, string $description,
            string $direction, string $amount, ?string $reference = null) use ($rows): void {
            $rows->push([
                'date' => $date, 'category' => $category, 'description' => $description,
                'direction' => $direction, 'amount_cents' => Money::toCents($amount),
                'reference' => $reference,
            ]);
        };

        $payments = ContributionPayment::query()->where('society_id', $society->id)
            ->where('society_membership_id', $membership->id)
            ->with('obligation')->get();
        $periodsByPayment = collect();
        if ($payments->isNotEmpty() && Schema::hasTable('legacy_receipt_obligation_links')) {
            $periodsByPayment = DB::table('legacy_receipt_obligation_links as l')
                ->join('legacy_receipt_period_attributions as a', 'a.id', '=', 'l.legacy_receipt_period_attribution_id')
                ->join('contribution_obligations as o', 'o.id', '=', 'l.contribution_obligation_id')
                ->join('contribution_payments as p', 'p.id', '=', 'l.contribution_payment_id')
                ->where('l.society_id', $society->id)
                ->whereIn('l.contribution_payment_id', $payments->pluck('id')->all())
                ->whereNull('l.reversed_at')->whereNull('a.reversed_at')
                ->whereColumn('a.contribution_payment_id', 'l.contribution_payment_id')
                ->whereColumn('o.society_membership_id', 'p.society_membership_id')
                ->get(['l.contribution_payment_id', 'o.period_start', 'l.amount_cents'])
                ->groupBy('contribution_payment_id');
        }

        foreach ($payments as $payment) {
            $periodParts = $periodsByPayment->get($payment->id, collect())
                ->map(fn ($p) => \Carbon\Carbon::parse($p->period_start)->format('M Y')
                    . ' (M ' . number_format((int) $p->amount_cents / 100, 2) . ')')->all();
            if ($periodParts !== []) {
                $description = 'Savings contribution — reviewed periods: ' . implode(', ', $periodParts);
            } elseif ($payment->obligation?->period_start) {
                $description = 'Savings contribution — ' . $payment->obligation->period_start->format('M Y');
            } else {
                $description = 'Savings contribution — original period not yet linked';
            }
            $append($payment->paid_on->toDateString(), 'Savings', $description,
                'paid', $payment->amount, $payment->payment_reference);
        }

        LoanDisbursement::query()->where('society_id', $society->id)
            ->whereHas('loan', fn ($q) => $q->where('society_membership_id', $membership->id))
            ->with('loan')->get()->each(fn ($p) => $append(
                $p->disbursed_on->toDateString(), 'Loan',
                'Loan disbursement — ' . $p->loan->loan_number,
                'received', $p->amount, $p->payment_reference
            ));

        LoanRepayment::query()->where('society_id', $society->id)
            ->whereHas('loan', fn ($q) => $q->where('society_membership_id', $membership->id))
            ->with('loan')->get()->each(fn ($p) => $append(
                $p->paid_on->toDateString(), 'Loan Repayment',
                'Loan repayment — ' . $p->loan->loan_number,
                'paid', $p->amount, $p->payment_reference
            ));

        LoanInterestCredit::query()->where('society_id', $society->id)
            ->where('beneficiary_membership_id', $membership->id)
            ->with(['loan', 'repayment'])->get()->each(fn ($c) => $append(
                $c->repayment->paid_on->toDateString(), 'Loan Interest Refund',
                'Refundable interest earned on loan ' . $c->loan->loan_number,
                'earned', $c->amount, $c->repayment->payment_reference
            ));

        ContributionPenaltyPayment::query()->where('society_id', $society->id)
            ->whereHas('penalty.obligation', fn ($q) => $q->where('society_membership_id', $membership->id))
            ->get()->each(fn ($p) => $append(
                $p->paid_on->toDateString(), 'Contribution Penalty',
                'Contribution penalty payment', 'paid', $p->amount, $p->payment_reference
            ));

        LoanPenaltyPayment::query()->where('society_id', $society->id)
            ->whereHas('loanPenalty.loan', fn ($q) => $q->where('society_membership_id', $membership->id))
            ->get()->each(fn ($p) => $append(
                $p->paid_on->toDateString(), 'Loan Penalty',
                'Loan penalty payment', 'paid', $p->amount, $p->payment_reference
            ));

        ContributionPenaltyWaiver::query()->where('society_id', $society->id)
            ->whereHas('penalty.obligation', fn ($q) => $q->where('society_membership_id', $membership->id))
            ->get()->each(fn ($p) => $append(
                $p->created_at->toDateString(), 'Contribution Penalty',
                'Contribution penalty waiver: ' . $p->reason, 'adjustment', $p->amount
            ));

        LoanPenaltyWaiver::query()->where('society_id', $society->id)
            ->whereHas('loanPenalty.loan', fn ($q) => $q->where('society_membership_id', $membership->id))
            ->get()->each(fn ($p) => $append(
                $p->created_at->toDateString(), 'Loan Penalty',
                'Loan penalty waiver: ' . $p->reason, 'adjustment', $p->amount
            ));

        // Period-review rows are deliberately NOT added as another money row.
        // Each original contribution appears exactly once in statements and CSV.
        return $rows->sortByDesc('date')->values();
    }
}
