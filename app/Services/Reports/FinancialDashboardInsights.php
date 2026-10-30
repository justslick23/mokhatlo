<?php

namespace App\Services\Reports;

use App\Models\ContributionPayment;
use App\Models\ContributionPenaltyPayment;
use App\Models\LedgerTransaction;
use App\Models\Loan;
use App\Models\LoanDisbursement;
use App\Models\LoanInterestCredit;
use App\Models\LoanPenaltyPayment;
use App\Models\LoanRepayment;
use App\Models\Society;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class FinancialDashboardInsights
{
    public function build(Society $society, int $months = 6): array
    {
        $months = in_array($months, [3, 6, 12], true) ? $months : 6;
        $cycle = $society->cycles()->where('status', 'active')->first();
        if (! $cycle) {
            $id = DB::table('legacy_financial_import_runs')
                ->where('society_id', $society->id)->orderByDesc('id')->value('cycle_id');
            $cycle = $id ? $society->cycles()->whereKey($id)->first() : null;
        }

        $today = CarbonImmutable::today($society->timezone ?: 'Africa/Maseru');
        $start = $today->startOfMonth()->subMonthsNoOverflow($months - 1);
        $end = $today->toDateString();
        $rows = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $start->addMonthsNoOverflow($i);
            $rows[$month->format('Y-m')] = [
                'label' => $month->format('M Y'), 'contributions' => 0,
                'disbursements' => 0, 'repayments' => 0, 'interest' => 0,
                'penalties' => 0,
            ];
        }

        if ($cycle) {
            $add = function ($records, string $dateField, string $field) use (&$rows): void {
                foreach ($records as $record) {
                    if (! $record->{$dateField}) {
                        continue;
                    }
                    $key = $record->{$dateField}->format('Y-m');
                    if (isset($rows[$key])) {
                        $rows[$key][$field] += Money::toCents($record->amount);
                    }
                }
            };
            $add(
                ContributionPayment::query()->where('society_id', $society->id)
                    ->where('cycle_id', $cycle->id)->whereBetween('paid_on', [$start->toDateString(), $end])->get(),
                'paid_on', 'contributions'
            );
            $add(
                LoanDisbursement::query()->where('society_id', $society->id)
                    ->whereHas('loan', fn ($q) => $q->where('cycle_id', $cycle->id))
                    ->whereBetween('disbursed_on', [$start->toDateString(), $end])->get(),
                'disbursed_on', 'disbursements'
            );
            $add(
                LoanRepayment::query()->where('society_id', $society->id)
                    ->whereHas('loan', fn ($q) => $q->where('cycle_id', $cycle->id))
                    ->whereBetween('paid_on', [$start->toDateString(), $end])->get(),
                'paid_on', 'repayments'
            );
            $credits = LoanInterestCredit::query()->where('society_id', $society->id)
                ->whereHas('loan', fn ($q) => $q->where('cycle_id', $cycle->id))
                ->whereHas('repayment', fn ($q) => $q->whereBetween('paid_on', [$start->toDateString(), $end]))
                ->with('repayment')->get();
            foreach ($credits as $credit) {
                $key = $credit->repayment->paid_on->format('Y-m');
                if (isset($rows[$key])) {
                    $rows[$key]['interest'] += Money::toCents($credit->amount);
                }
            }
            $add(
                ContributionPenaltyPayment::query()->where('society_id', $society->id)
                    ->whereHas('penalty', fn ($q) => $q->where('cycle_id', $cycle->id))
                    ->whereBetween('paid_on', [$start->toDateString(), $end])->get(),
                'paid_on', 'penalties'
            );
            $add(
                LoanPenaltyPayment::query()->where('society_id', $society->id)
                    ->whereHas('loanPenalty.loan', fn ($q) => $q->where('cycle_id', $cycle->id))
                    ->whereBetween('paid_on', [$start->toDateString(), $end])->get(),
                'paid_on', 'penalties'
            );
        }

        $statuses = ['approved' => 0, 'disbursed' => 0, 'settled' => 0, 'rejected' => 0];
        if ($cycle) {
            $counts = Loan::query()->where('society_id', $society->id)
                ->where('cycle_id', $cycle->id)
                ->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')
                ->pluck('aggregate', 'status');
            foreach ($statuses as $status => $_) {
                $statuses[$status] = (int) ($counts[$status] ?? 0);
            }
        }

        return [
            'months' => $months,
            'cycle' => $cycle,
            'monthly' => array_values($rows),
            'loan_statuses' => $statuses,
            'recent_transactions' => LedgerTransaction::query()
                ->where('society_id', $society->id)->with(['creator', 'lines'])
                ->orderByDesc('posting_date')->orderByDesc('id')->limit(8)->get(),
            'recent_loans' => $cycle
                ? Loan::query()->where('society_id', $society->id)->where('cycle_id', $cycle->id)
                    ->with(['membership.user', 'lender.user'])->orderByDesc('id')->limit(6)->get()
                : collect(),
        ];
    }
}
