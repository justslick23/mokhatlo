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
    protected $signature   = 'reports:month-end {--month= : Month (1-12)} {--year= : Year}';
    protected $description = 'Generate and email month-end society reports and member statements';

    public function handle(): void
    {
        // Default to PREVIOUS month so when it runs on the 1st
        // it always reports on the month just ended
        $previousMonth = now()->subMonth();

        $month  = (int) ($this->option('month') ?: $previousMonth->month);
        $year   = (int) ($this->option('year')  ?: $previousMonth->year);
        $period = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        $this->info("Generating reports for: {$period->format('F Y')}");

        Society::with(['activeCycle', 'members.user'])
            ->where('status', 'active')
            ->each(function (Society $society) use ($month, $year, $period) {

                $cycle = $society->activeCycle;

                if (!$cycle) {
                    $this->warn("  [{$society->name}] No active cycle. Skipping.");
                    return;
                }

                $this->info("  Processing [{$society->name}]...");

                $summary = $this->buildSocietySummary($society, $cycle, $month, $year);

                // ── Society-wide report ───────────────────────────────
                $this->generateSocietyReport($society, $cycle, $summary, $period);

                // ── Individual member statements ──────────────────────
                $society->members()
                    ->where('status', 'active')
                    ->with('user')
                    ->each(function (Member $member) use ($society, $cycle, $summary, $period, $month, $year) {
                        $this->generateMemberStatement(
                            $society, $cycle, $member, $summary, $period, $month, $year
                        );
                    });

                $this->info("  [{$society->name}] Done ✓");
            });

        $this->info('All reports generated.');
    }

    // ─────────────────────────────────────────────────────────────
    // Society summary
    // ─────────────────────────────────────────────────────────────
    protected function buildSocietySummary(Society $society, $cycle, int $month, int $year): array
    {
        // Helper: base query scoped to this society/cycle/month
        $base = function (string $type) use ($society, $cycle, $month, $year): float {
            return Transaction::where('society_id', $society->id)
                ->where('cycle_id', $cycle->id)
                ->where('type', $type)
                ->whereMonth('transaction_date', $month)
                ->whereYear('transaction_date', $year)
                ->sum('amount');
        };

        // Helper: cycle-to-date totals (no month filter)
        $cycleTotal = function (string $type) use ($society, $cycle): float {
            return Transaction::where('society_id', $society->id)
                ->where('cycle_id', $cycle->id)
                ->where('type', $type)
                ->sum('amount');
        };

        // ── This month ────────────────────────────────────────────
        $totalContributions = $base('contribution');
        $totalPenalties     = $base('penalty');
        $totalDisbursed     = $base('loan_disbursement');
        $totalRepayments    = $base('loan_repayment');

        // ── Cycle-to-date ─────────────────────────────────────────
        $cycleContributions = $cycleTotal('contribution');
        $cycleDisbursed     = $cycleTotal('loan_disbursement');
        $cycleRepayments    = $cycleTotal('loan_repayment');

        // ── Active loans ──────────────────────────────────────────
        $activeLoans = Loan::where('society_id', $society->id)
            ->where('cycle_id', $cycle->id)
            ->whereIn('status', ['active', 'overdue'])
            ->with('member.user')
            ->get();

        // ── Member-by-member breakdown ────────────────────────────
        $memberBreakdown = $society->members()
            ->where('status', 'active')
            ->with('user')
            ->get()
            ->map(function (Member $member) use ($society, $cycle, $month, $year) {

                $contributed = Transaction::where('society_id', $society->id)
                    ->where('cycle_id', $cycle->id)
                    ->where('member_id', $member->id)
                    ->where('type', 'contribution')
                    ->whereMonth('transaction_date', $month)
                    ->whereYear('transaction_date', $year)
                    ->sum('amount');

                $penalties = Transaction::where('society_id', $society->id)
                    ->where('cycle_id', $cycle->id)
                    ->where('member_id', $member->id)
                    ->where('type', 'penalty')
                    ->whereMonth('transaction_date', $month)
                    ->whereYear('transaction_date', $year)
                    ->sum('amount');

                $activeLoan = Loan::where('society_id', $society->id)
                    ->where('cycle_id', $cycle->id)
                    ->where('member_id', $member->id)
                    ->whereIn('status', ['active', 'overdue'])
                    ->first();

                return [
                    'member'              => $member,
                    'contributed'         => (float) $contributed,
                    'penalties'           => (float) $penalties,
                    'has_active_loan'     => (bool) $activeLoan,
                    'outstanding_balance' => (float) ($activeLoan?->outstanding_balance ?? 0),
                    'loan_status'         => $activeLoan?->status ?? 'none',
                ];
            });

        return [
            // Period
            'month'               => $month,
            'year'                => $year,
            // This month
            'total_contributions' => (float) $totalContributions,
            'total_penalties'     => (float) $totalPenalties,
            'total_disbursed'     => (float) $totalDisbursed,
            'total_repayments'    => (float) $totalRepayments,
            // Cycle-wide pool
            'pool_balance'        => round($cycleContributions + $cycleRepayments - $cycleDisbursed, 2),
            'available_balance'   => $society->availableBalance($cycle->id),
            // Loans
            'active_loans'        => $activeLoans,
            'active_loans_count'  => $activeLoans->count(),
            'total_outstanding'   => (float) $activeLoans->sum('outstanding_balance'),
            // Members
            'member_breakdown'    => $memberBreakdown,
            'total_members'       => $memberBreakdown->count(),
            'defaulters_count'    => $memberBreakdown->where('contributed', 0)->count(),
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Society report → PDF → email officers
    // ─────────────────────────────────────────────────────────────
    protected function generateSocietyReport(
        Society $society,
        $cycle,
        array $summary,
        Carbon $period
    ): void {
        $pdf = Pdf::loadView('reports.society-month-end', [
            'society' => $society,
            'cycle'   => $cycle,
            'summary' => $summary,
            'period'  => $period,
        ])->setPaper('a4', 'portrait');

        $filename = "{$society->id}-society-report-{$period->format('Y-m')}.pdf";
        $pdfOutput = $pdf->output();

        $officers = $society->members()
            ->with('user')
            ->whereIn('role', ['chairman', 'treasurer'])
            ->get();

        if ($officers->isEmpty()) {
            $this->warn("    [{$society->name}] No officers found to email report to.");
            return;
        }

        foreach ($officers as $officer) {
            Mail::to($officer->user->email)
                ->send(new SocietyMonthEndReport(
                    society:    $society,
                    period:     $period,
                    summary:    $summary,       // ← fix: pass summary to mailable
                    pdfContent: $pdfOutput,
                    filename:   $filename,
                ));

            $this->info("    Society report → {$officer->user->email}");
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Member statement → PDF → email member
    // ─────────────────────────────────────────────────────────────
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
            ->orderBy('transaction_date')
            ->get();

        $penalties = Transaction::where('society_id', $society->id)
            ->where('cycle_id', $cycle->id)
            ->where('member_id', $member->id)
            ->where('type', 'penalty')
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->orderBy('transaction_date')
            ->get();

        $loans = Loan::where('society_id', $society->id)
            ->where('cycle_id', $cycle->id)
            ->where('member_id', $member->id)
            ->orderBy('issue_date')
            ->get();

        $totalCycleContributions = Transaction::where('society_id', $society->id)
            ->where('cycle_id', $cycle->id)
            ->where('member_id', $member->id)
            ->where('type', 'contribution')
            ->sum('amount');

        // Share % based on cycle pool
        $cyclePoolTotal = Transaction::where('society_id', $society->id)
            ->where('cycle_id', $cycle->id)
            ->where('type', 'contribution')
            ->sum('amount');

        $sharePercent = $cyclePoolTotal > 0
            ? round(($totalCycleContributions / $cyclePoolTotal) * 100, 2)
            : 0;

        $memberData = [
            'contributions'           => $contributions,
            'penalties'               => $penalties,
            'loans'                   => $loans,
            'total_contributed_month' => (float) $contributions->sum('amount'),
            'total_penalties_month'   => (float) $penalties->sum('amount'),
            'total_contributed_cycle' => (float) $totalCycleContributions,
            'share_percent'           => $sharePercent,
            'share_value'             => round($summary['pool_balance'] * ($sharePercent / 100), 2),
            'active_loan'             => $loans->whereIn('status', ['active', 'overdue'])->first(),
        ];

        $pdf = Pdf::loadView('reports.member-statement', [
            'society'    => $society,
            'cycle'      => $cycle,
            'member'     => $member,
            'period'     => $period,
            'memberData' => $memberData,
        ])->setPaper('a4', 'portrait');

        $filename  = "{$society->id}-member-{$member->id}-{$period->format('Y-m')}.pdf";
        $pdfOutput = $pdf->output();

        Mail::to($member->user->email)
            ->send(new MemberMonthEndStatement(
                society:    $society,
                member:     $member,
                period:     $period,
                memberData: $memberData,    // ← fix: pass memberData to mailable
                pdfContent: $pdfOutput,
                filename:   $filename,
            ));

        $this->info("    Statement → {$member->user->email}");
    }
}