<?php

namespace App\Services\Reports;

use App\Models\ContributionObligation;
use App\Models\ContributionPayment;
use App\Models\ContributionPenalty;
use App\Models\LedgerAccount;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanPenalty;
use App\Models\LoanRepaymentAllocation;
use App\Models\Society;
use App\Services\Accounting\SocietyAssetAccounts;
use App\Services\Contributions\ContributionObligationCoverage;
use App\Services\Loans\LoanBalances;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class SocietyFinancialReport
{
    public function __construct(
        private readonly SocietyAssetAccounts $assets,
        private readonly LoanBalances $loanBalances,
        private readonly ContributionObligationCoverage $coverage,
    ) {}

    /**
     * No requested cycle: active cycle first, otherwise latest imported cycle.
     * Explicit cycle ID: society-scoped selection, including draft historical cycles.
     * Imported savings are counted ONCE from contribution_payments. Reviewed links
     * affect obligation coverage only, never cash received or member capital.
     */
    public function build(Society $society, ?int $cycleId = null): array
    {
        $cycle = $cycleId !== null
            ? $society->cycles()->whereKey($cycleId)->with('policy')->firstOrFail()
            : $society->cycles()->where('status', 'active')->with('policy')->first();

        if ($cycle === null) {
            $importedCycleId = DB::table('legacy_financial_import_runs')
                ->where('society_id', $society->id)->orderByDesc('id')->value('cycle_id');
            $cycle = $importedCycleId
                ? $society->cycles()->whereKey($importedCycleId)->with('policy')->first()
                : null;
        }

        $today = CarbonImmutable::today($society->timezone ?: 'Africa/Maseru');
        $cashBalances = [];
        foreach (['CASH_ON_HAND' => 'Cash', 'BANK_ACCOUNT' => 'Bank', 'MOBILE_MONEY' => 'Mobile Money'] as $code => $label) {
            $account = LedgerAccount::query()->where('society_id', $society->id)
                ->where('code', $code)->first();
            $cashBalances[$code] = [
                'label' => $label,
                'amount' => $account ? $this->assets->balanceCents($account) : 0,
            ];
        }

        $result = [
            'cycle' => $cycle,
            'accounts' => $cashBalances,
            'liquid_assets' => array_sum(array_column($cashBalances, 'amount')),
            'contributions_received' => 0,
            'contributions_required' => 0,
            'contributions_outstanding' => 0,
            'contributions_overdue' => 0,
            'loan_principal_outstanding' => 0,
            'loan_interest_outstanding' => 0,
            'loan_interest_collected' => 0,
            'loan_instalments_overdue' => 0,
            'loan_penalties_outstanding' => 0,
            'contribution_penalties_outstanding' => 0,
            'active_loan_count' => 0,
            'historical_cycle' => false,
            'historical_excess_savings' => 0,
            'historical_compliance_scope_note' => null,
        ];
        if ($cycle === null) {
            return $result;
        }

        $isImported = DB::table('legacy_financial_import_runs')
            ->where('society_id', $society->id)->where('cycle_id', $cycle->id)->exists();
        $result['historical_cycle'] = $isImported;

        // Cash / member savings is the actual paid receipt history, not the sum
        // of obligation allocations. This preserves extra M1,500 member savings.
        ContributionPayment::query()->where('society_id', $society->id)
            ->where('cycle_id', $cycle->id)->orderBy('id')
            ->chunkById(200, function ($payments) use (&$result) {
                foreach ($payments as $payment) {
                    $result['contributions_received'] += Money::toCents($payment->amount);
                }
            });

        if ($isImported) {
            $coverage = $this->coverage->forCycle((int) $society->id, (int) $cycle->id);
            $result['contributions_required'] = $coverage['totals']['required_cents'];
            $result['contributions_outstanding'] = $coverage['totals']['outstanding_cents'];
            $result['historical_excess_savings'] = $coverage['totals']['excess_cents'];
            $result['historical_compliance_scope_note'] = $coverage['notice'];
            foreach ($coverage['rows'] as $row) {
                $due = CarbonImmutable::parse($row['due_date'])->addDays(
                    (int) ($cycle->policy?->contribution_grace_days ?? 0)
                );
                if ($today->greaterThan($due)) {
                    $result['contributions_overdue'] += (int) $row['outstanding_cents'];
                }
            }
        } else {
            ContributionObligation::query()
                ->where('society_id', $society->id)->where('cycle_id', $cycle->id)
                ->withSum('payments as paid_total', 'amount')->orderBy('id')
                ->chunkById(200, function ($obligations) use (&$result, $today, $cycle) {
                    foreach ($obligations as $obligation) {
                        $required = Money::toCents($obligation->required_amount);
                        $paid = Money::toCents((string) ($obligation->paid_total ?? '0.00'));
                        $remaining = max(0, $required - $paid);
                        $result['contributions_required'] += $required;
                        $result['contributions_outstanding'] += $remaining;
                        $cutoff = CarbonImmutable::parse($obligation->due_date->toDateString())
                            ->addDays((int) ($cycle->policy?->contribution_grace_days ?? 0));
                        if ($today->greaterThan($cutoff)) {
                            $result['contributions_overdue'] += $remaining;
                        }
                    }
                });
        }

        Loan::query()->where('society_id', $society->id)
            ->where('cycle_id', $cycle->id)->whereIn('status', ['disbursed', 'settled'])
            ->orderBy('id')->chunkById(100, function ($loans) use (&$result) {
                foreach ($loans as $loan) {
                    $balance = $this->loanBalances->summary($loan);
                    $result['loan_principal_outstanding'] += $balance['principal_outstanding'];
                    $result['loan_interest_outstanding'] += $balance['interest_outstanding'];
                    if ($loan->status === 'disbursed') {
                        $result['active_loan_count']++;
                    }
                }
            });

        LoanRepaymentAllocation::query()->whereHas('repayment', function ($query) use ($society, $cycle) {
            $query->where('society_id', $society->id)
                ->whereHas('loan', fn ($q) => $q->where('cycle_id', $cycle->id));
        })->orderBy('id')->chunkById(300, function ($rows) use (&$result) {
            foreach ($rows as $row) {
                $result['loan_interest_collected'] += Money::toCents($row->interest_amount);
            }
        });

        LoanInstallment::query()->where('society_id', $society->id)
            ->whereDate('due_date', '<', $today->toDateString())
            ->whereHas('loan', fn ($query) => $query->where('cycle_id', $cycle->id)
                ->whereIn('status', ['disbursed', 'settled']))
            ->with('allocations')->orderBy('id')->chunkById(200, function ($rows) use (&$result) {
                foreach ($rows as $installment) {
                    $paid = $installment->allocations->sum(fn ($a) =>
                        Money::toCents($a->principal_amount) + Money::toCents($a->interest_amount));
                    $result['loan_instalments_overdue'] += max(
                        0, Money::toCents($installment->total_due) - $paid
                    );
                }
            });

        LoanPenalty::query()->where('society_id', $society->id)
            ->whereHas('loan', fn ($query) => $query->where('cycle_id', $cycle->id))
            ->with(['payments', 'waivers'])->orderBy('id')
            ->chunkById(100, function ($rows) use (&$result) {
                foreach ($rows as $penalty) {
                    $result['loan_penalties_outstanding'] += $penalty->outstandingCents();
                }
            });

        ContributionPenalty::query()->where('society_id', $society->id)
            ->where('cycle_id', $cycle->id)->with(['payments', 'waivers'])
            ->orderBy('id')->chunkById(100, function ($rows) use (&$result) {
                foreach ($rows as $penalty) {
                    $result['contribution_penalties_outstanding'] += $penalty->outstandingCents();
                }
            });

        return $result;
    }
}
