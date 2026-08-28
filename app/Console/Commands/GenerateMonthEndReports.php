<?php

namespace App\Console\Commands;

use App\Models\Society;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\Loan;
use App\Mail\SocietyMonthEndReport;
use App\Mail\MemberMonthEndStatement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class GenerateMonthEndReports extends Command
{
    protected $signature = 'reports:month-end {--month=} {--year=}';
    protected $description = 'Generate and email month-end society reports and member statements';

    public function handle(): void
    {
        $previousMonth = now()->subMonth();

        $month = (int) ($this->option('month') ?: $previousMonth->month);
        $year  = (int) ($this->option('year') ?: $previousMonth->year);

        $period = Carbon::create($year, $month, 1)->endOfMonth();

        $this->info("Generating reports for {$period->format('F Y')}");

        Society::with(['activeCycle', 'members.user'])
            ->where('status', 'active')
            ->each(function (Society $society) use ($month, $year, $period) {

                $cycle = $society->activeCycle;

                if (!$cycle) {
                    $this->warn("{$society->name}: No active cycle.");
                    return;
                }

                $summary = $this->buildSocietySummary(
                    $society,
                    $cycle,
                    $month,
                    $year
                );

                $this->generateSocietyReport(
                    $society,
                    $cycle,
                    $summary,
                    $period
                );

                $society->members()
                    ->where('status', 'active')
                    ->with('user')
                    ->each(function (Member $member) use (
                        $society,
                        $cycle,
                        $summary,
                        $period,
                        $month,
                        $year
                    ) {
                        $this->generateMemberStatement(
                            $society,
                            $cycle,
                            $member,
                            $summary,
                            $period,
                            $month,
                            $year
                        );
                    });

                $this->info("{$society->name}: Done.");
            });

        $this->info('All reports generated.');
    }

    protected function buildSocietySummary(
        Society $society,
        $cycle,
        int $month,
        int $year
    ): array {

        $base = function ($type) use ($society, $cycle, $month, $year) {
            return (float) Transaction::where('society_id', $society->id)
                ->where('cycle_id', $cycle->id)
                ->where('type', $type)
                ->whereMonth('transaction_date', $month)
                ->whereYear('transaction_date', $year)
                ->sum('amount');
        };

        $cycleTotal = function ($type) use ($society, $cycle) {
            return (float) Transaction::where('society_id', $society->id)
                ->where('cycle_id', $cycle->id)
                ->where('type', $type)
                ->sum('amount');
        };

        $totalContributions = $base('contribution');
        $totalPenalties = $base('penalty');
        $totalDisbursed = $base('loan_disbursement');
        $totalRepayments = $base('loan_repayment');
        $totalInterestCollected = $base('loan_interest');

        $cycleContributions = $cycleTotal('contribution');
        $cyclePenalties = $cycleTotal('penalty');
        $cycleRepayments = $cycleTotal('loan_repayment');
        $cycleDisbursed = $cycleTotal('loan_disbursement');
        $cycleInterestCollected = $cycleTotal('loan_interest');

        $members = $society->members()
            ->where('status', 'active')
            ->with('user')
            ->get();

        $cycleStart = Carbon::parse($cycle->start_date)->startOfMonth();
        $reportMonth = Carbon::create($year, $month, 1)->startOfMonth();

        $totalContributionDebt = 0;
        $totalContributionPenaltyDebt = 0;
        $totalLoanDebt = 0;

        $debtors = [];

        foreach ($members as $member) {

            $memberContributionDebt = 0;
            $memberPenaltyDebt = 0;

            $cursor = $cycleStart->copy();

            while ($cursor <= $reportMonth) {

                $required = (float) $society->minimum_contribution;

                $paid = (float) Transaction::where('society_id', $society->id)
                    ->where('cycle_id', $cycle->id)
                    ->where('member_id', $member->id)
                    ->where('type', 'contribution')
                    ->whereMonth('transaction_date', $cursor->month)
                    ->whereYear('transaction_date', $cursor->year)
                    ->sum('amount');

                if ($paid < $required) {

                    $shortfall = $required - $paid;

                    $monthName = $cursor->format('F Y');

                    $penaltyExists = Transaction::where('society_id', $society->id)
                        ->where('cycle_id', $cycle->id)
                        ->where('member_id', $member->id)
                        ->where('type', 'penalty')
                        ->whereRaw(
                            "notes LIKE ?",
                            ["%missed contribution for {$monthName}%"]
                        )
                        ->exists();

                    $penalty = $penaltyExists
                        ? 0
                        : round(
                            $shortfall *
                            ($society->penalty_value / 100),
                            2
                        );

                    $memberContributionDebt += $shortfall;
                    $memberPenaltyDebt += $penalty;
                }

                $cursor->addMonth();
            }

            $memberLoanDebt = (float) Loan::where('society_id', $society->id)
                ->where('cycle_id', $cycle->id)
                ->where('member_id', $member->id)
                ->where('outstanding_balance', '>', 0)
                ->sum('outstanding_balance');

            $memberTotalDebt =
                $memberContributionDebt +
                $memberPenaltyDebt +
                $memberLoanDebt;

            if ($memberTotalDebt > 0) {
                $debtors[] = [
                    'member' => $member->user->name,
                    'contribution_debt' => $memberContributionDebt,
                    'penalty_debt' => $memberPenaltyDebt,
                    'loan_debt' => $memberLoanDebt,
                    'total_debt' => $memberTotalDebt,
                ];
            }

            $totalContributionDebt += $memberContributionDebt;
            $totalContributionPenaltyDebt += $memberPenaltyDebt;
            $totalLoanDebt += $memberLoanDebt;
        }

        // ===== NEW: Build member breakdown for PDF =====
        $memberBreakdown = $members->map(function ($member) use (
            $society,
            $cycle,
            $month,
            $year
        ) {
            $contributed = (float) Transaction::where('society_id', $society->id)
                ->where('cycle_id', $cycle->id)
                ->where('member_id', $member->id)
                ->where('type', 'contribution')
                ->whereMonth('transaction_date', $month)
                ->whereYear('transaction_date', $year)
                ->sum('amount');

            $penalties = (float) Transaction::where('society_id', $society->id)
                ->where('cycle_id', $cycle->id)
                ->where('member_id', $member->id)
                ->where('type', 'penalty')
                ->whereMonth('transaction_date', $month)
                ->whereYear('transaction_date', $year)
                ->sum('amount');

            $interestPaid = (float) Transaction::where('society_id', $society->id)
                ->where('cycle_id', $cycle->id)
                ->where('member_id', $member->id)
                ->where('type', 'loan_interest')
                ->whereMonth('transaction_date', $month)
                ->whereYear('transaction_date', $year)
                ->sum('amount');

            $activeLoan = Loan::where('society_id', $society->id)
                ->where('cycle_id', $cycle->id)
                ->where('member_id', $member->id)
                ->whereIn('status', ['active', 'overdue'])
                ->first();

            $outstandingBalance = $activeLoan?->outstanding_balance ?? 0;
            $loanStatus = $activeLoan?->status ?? 'none';

            return [
                'member' => $member,
                'contributed' => $contributed,
                'penalties' => $penalties,
                'interest_paid' => $interestPaid,
                'loan_status' => $loanStatus,
                'outstanding_balance' => $outstandingBalance,
            ];
        });

        // ===== NEW: Get all active loans =====
        $activeLoans = Loan::where('society_id', $society->id)
            ->where('cycle_id', $cycle->id)
            ->whereIn('status', ['active', 'overdue'])
            ->with('member.user')
            ->get();

        $defaultersCount = $society->members()
            ->where('status', 'active')
            ->whereDoesntHave('transactions', function ($q) use ($month, $year) {
                $q->where('type', 'contribution')
                  ->whereMonth('transaction_date', $month)
                  ->whereYear('transaction_date', $year);
            })
            ->count();

        return [
            'month' => $month,
            'year' => $year,

            'total_contributions' => $totalContributions,
            'total_penalties' => $totalPenalties,
            'total_disbursed' => $totalDisbursed,
            'total_repayments' => $totalRepayments,
            'total_interest_collected' => $totalInterestCollected,
            'defaulters_count' => $defaultersCount,
            'cycle_contributions' => $cycleContributions,
            'cycle_penalties' => $cyclePenalties,
            'cycle_interest_collected' => $cycleInterestCollected,

            'pool_balance' => round(
                $cycleContributions +
                $cycleRepayments -
                $cycleDisbursed,
                2
            ),

            'available_balance' =>
                $society->availableBalance($cycle->id),

            'active_loans_count' => $activeLoans->count(),
            'total_outstanding' =>
                (float) $activeLoans->sum('outstanding_balance'),

            'total_members' => $members->count(),

            'total_contribution_debt' =>
                $totalContributionDebt,

            'total_contribution_penalty_debt' =>
                $totalContributionPenaltyDebt,

            'total_loan_debt' =>
                $totalLoanDebt,

            'grand_total_debt' =>
                $totalContributionDebt +
                $totalContributionPenaltyDebt +
                $totalLoanDebt,

            'debtors' => $debtors,
            
            // ===== NEW FIELDS =====
            'member_breakdown' => $memberBreakdown,
            'active_loans' => $activeLoans,
        ];
    }

    protected function generateSocietyReport(
        Society $society,
        $cycle,
        array $summary,
        Carbon $period
    ): void {

        $pdf = Pdf::loadView('reports.society-month-end', [
            'society' => $society,
            'cycle' => $cycle,
            'summary' => $summary,
            'period' => $period,
        ]);

        $filename = "{$society->id}-society-report-{$period->format('Y-m')}.pdf";

        $officers = $society->members()
            ->whereIn('role', ['chairman', 'treasurer', 'member'])
            ->with('user')
            ->get();

        foreach ($officers as $officer) {

            Mail::to($officer->user->email)
                ->send(new SocietyMonthEndReport(
                    society: $society,
                    period: $period,
                    summary: $summary,
                    pdfContent: $pdf->output(),
                    filename: $filename
                ));

            $this->info("Society report sent to {$officer->user->email}");
        }
    }

    protected function generateMemberStatement(
        Society $society,
        $cycle,
        Member $member,
        array $summary,
        Carbon $period,
        int $month,
        int $year
    ): void {

        $contributions = Transaction::where('society_id', $society->id)
            ->where('cycle_id', $cycle->id)
            ->where('member_id', $member->id)
            ->where('type', 'contribution')
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->get();

        $penalties = Transaction::where('society_id', $society->id)
            ->where('cycle_id', $cycle->id)
            ->where('member_id', $member->id)
            ->where('type', 'penalty')
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->get();

        $loanInterest = Transaction::where('society_id', $society->id)
            ->where('cycle_id', $cycle->id)
            ->where('member_id', $member->id)
            ->where('type', 'loan_interest')
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->get();

        $loans = Loan::where('society_id', $society->id)
            ->where('cycle_id', $cycle->id)
            ->where('member_id', $member->id)
            ->get();

        // ===== NEW: Calculate monthly totals =====
        $totalContributedMonth = (float) $contributions->sum('amount');
        $totalPenaltiesMonth = (float) $penalties->sum('amount');
        $totalInterestMonth = (float) $loanInterest->sum('amount');

        // ===== NEW: Calculate cycle totals =====
        $totalContributedCycle = (float) Transaction::where('society_id', $society->id)
            ->where('cycle_id', $cycle->id)
            ->where('member_id', $member->id)
            ->where('type', 'contribution')
            ->sum('amount');

        $totalInterestCycle = (float) Transaction::where('society_id', $society->id)
            ->where('cycle_id', $cycle->id)
            ->where('member_id', $member->id)
            ->where('type', 'loan_interest')
            ->sum('amount');

        // ===== NEW: Calculate debt from summary debtors array =====
        $memberDebtInfo = collect($summary['debtors'])
            ->firstWhere('member', $member->user->name);

        $contributionDebt = $memberDebtInfo['contribution_debt'] ?? 0;
        $contributionPenaltyDebt = $memberDebtInfo['penalty_debt'] ?? 0;
        $loanDebt = $memberDebtInfo['loan_debt'] ?? 0;
        $totalDebt = $memberDebtInfo['total_debt'] ?? 0;
        $totalCycleValue =
        $summary['cycle_contributions']
        + $summary['cycle_penalties']
        + $summary['cycle_interest_collected'];
    
    $memberSharePercent = $summary['cycle_contributions'] > 0
        ? round(($totalContributedCycle / $summary['cycle_contributions']) * 100, 2)
        : 0;
    
    $memberShareValue = ($memberSharePercent / 100) * $totalCycleValue;

        // ===== COMPLETE memberData array =====
        $memberData = [
            'contributions' => $contributions,
            'penalties' => $penalties,
            'loan_interest' => $loanInterest,
            'loans' => $loans,
            'active_loan' => $loans->whereIn('status', ['active', 'overdue'])->first(),
            
            // Monthly totals
            'total_contributed_month' => $totalContributedMonth,
            'total_penalties_month' => $totalPenaltiesMonth,
            'total_interest_month' => $totalInterestMonth,
            
            // Cycle totals
            'total_contributed_cycle' => $totalContributedCycle,
            'total_interest_cycle' => $totalInterestCycle,
            
            // Debt
            'contribution_debt' => $contributionDebt,
            'contribution_penalty_debt' => $contributionPenaltyDebt,
            'loan_debt' => $loanDebt,
            'grand_total_debt' => $totalDebt,
            
            // Share
            'share_percent' => $memberSharePercent,
            'share_value' => $memberShareValue,
        ];

        $pdf = Pdf::loadView('reports.member-statement', [
            'society' => $society,
            'cycle' => $cycle,
            'member' => $member,
            'period' => $period,
            'memberData' => $memberData,
        ]);

        $filename =
            "{$society->id}-member-{$member->id}-{$period->format('Y-m')}.pdf";

        Mail::to($member->user->email)
            ->send(new MemberMonthEndStatement(
                society: $society,
                member: $member,
                period: $period,
                memberData: $memberData,
                pdfContent: $pdf->output(),
                filename: $filename
            ));

        $this->info("Statement sent to {$member->user->email}");
    }
}