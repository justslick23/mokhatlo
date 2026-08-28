<?php

namespace App\Console\Commands;

use App\Models\Society;
use App\Models\Loan;
use App\Models\Transaction;
use App\Mail\ContributionReminder;
use App\Mail\PenaltyApplied;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class ProcessContributionDeadlines extends Command
{
    protected $signature =
        'contributions:process-deadlines
        {--month= : Optional month in YYYY-MM format}';

    protected $description =
        'Send reminders and apply penalties for late contributions and overdue loans';

    const REMINDER_DAYS_BEFORE = 5;

    public function handle(): void
    {
        /**
         * If user passes:
         * php artisan contributions:process-deadlines --month=2026-03
         */

        if ($this->option('month')) {

            try {
                $today = Carbon::parse(
                    $this->option('month') . '-01'
                )->startOfMonth();

            } catch (\Exception $e) {
                $this->error('Invalid month format. Use YYYY-MM');
                return;
            }

        } else {
            $today = Carbon::today();
        }

        $previousMonth = $today->copy()->subMonth();

        $this->info("Running for: ".$today->format('F Y'));

        Society::with(['activeCycle', 'members.user'])
            ->where('status', 'active')
            ->each(function (Society $society) use ($today, $previousMonth) {

                $cycle = $society->activeCycle;

                if (!$cycle) {
                    $this->warn("[{$society->name}] No active cycle.");
                    return;
                }

                $this->info("Processing {$society->name}");

                /**
                 * REMINDERS
                 * only if running current month
                 */
                if ($today->isCurrentMonth()) {

                    $dueDate = $today->copy()->day(
                        min(
                            $society->contribution_due_day,
                            $today->daysInMonth
                        )
                    );

                    $daysLeft = $today->diffInDays($dueDate, false);

                    if (
                        $daysLeft > 0 &&
                        $daysLeft <= self::REMINDER_DAYS_BEFORE
                    ) {

                        $defaulters = $this->getDefaultersForMonth(
                            $society,
                            $cycle->id,
                            $today->month,
                            $today->year
                        );

                        if ($defaulters->isNotEmpty()) {
                            $this->sendReminders(
                                $society,
                                $defaulters,
                                $daysLeft
                            );
                        }
                    }
                }

                /**
                 * PENALTIES
                 * if custom month passed OR first day live run
                 */
                if (
                    $this->option('month') ||
                    now()->day === 1
                ) {

                    $this->applyContributionPenalties(
                        $society,
                        $cycle,
                        $previousMonth
                    );

                    $this->applyLoanPenalties(
                        $society,
                        $cycle,
                        $today
                    );
                }
            });

        $this->info('Done.');
    }

    protected function getDefaultersForMonth(
        Society $society,
        int $cycleId,
        int $month,
        int $year
    ) {
        $paidIds = Transaction::where('society_id', $society->id)
            ->where('cycle_id', $cycleId)
            ->where('type', 'contribution')
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->pluck('member_id')
            ->toArray();

        return $society->members()
            ->where('status', 'active')
            ->whereNotIn('id', $paidIds)
            ->with('user')
            ->get();
    }

    protected function sendReminders(
        Society $society,
        $members,
        int $daysLeft
    ): void {
        foreach ($members as $member) {

            Mail::to($member->user->email)
                ->send(new ContributionReminder(
                    $society,
                    $member,
                    $daysLeft
                ));

            $this->info("Reminder → {$member->user->email}");
        }
    }

    protected function applyContributionPenalties(
        Society $society,
        $cycle,
        Carbon $previousMonth
    ): void
    {
        $cycleStart = Carbon::parse($cycle->start_date)->startOfMonth();
        $endMonth   = $previousMonth->copy()->startOfMonth();
    
        $members = $society->members()
            ->where('status', 'active')
            ->with('user')
            ->get();
    
        foreach ($members as $member) {
    
            $monthsOwed = [];
            $totalContributionOwed = 0;
            $totalContributionPenalty = 0;
    
            $monthCursor = $cycleStart->copy();
    
            while ($monthCursor <= $endMonth) {
    
                $monthName = $monthCursor->format('F Y');
    
                $paidContribution = Transaction::where('society_id', $society->id)
                    ->where('member_id', $member->id)
                    ->where('cycle_id', $cycle->id)
                    ->where('type', 'contribution')
                    ->whereMonth('transaction_date', $monthCursor->month)
                    ->whereYear('transaction_date', $monthCursor->year)
                    ->exists();
    
                if (!$paidContribution) {
    
                    $fee = $society->minimum_contribution;
    
                    $penalty = round(
                        $fee * ($society->penalty_value / 100),
                        2
                    );
    
                    $penaltyExists = Transaction::where('society_id', $society->id)
                        ->where('member_id', $member->id)
                        ->where('cycle_id', $cycle->id)
                        ->where('type', 'penalty')
                        ->whereRaw(
                            "notes LIKE ?",
                            ["%missed contribution for {$monthName}%"]
                        )
                        ->exists();
    
                    $penaltyDueNow = $penaltyExists ? 0 : $penalty;
    
                    $monthsOwed[] = [
                        'month' => $monthName,
                        'contribution_fee' => $fee,
                        'penalty_due_now' => $penaltyDueNow,
                        'month_total' => $fee + $penaltyDueNow,
                    ];
    
                    $totalContributionOwed += $fee;
                    $totalContributionPenalty += $penaltyDueNow;
                }
    
                $monthCursor->addMonth();
            }
    
            /**
             * Loan balances
             */
            $loanBalance = Loan::where('society_id', $society->id)
                ->where('cycle_id', $cycle->id)
                ->where('member_id', $member->id)
                ->where('outstanding_balance', '>', 0)
                ->sum('outstanding_balance');
    
            /**
             * Grand total
             */
            $grandTotal =
                $totalContributionOwed +
                $totalContributionPenalty +
                $loanBalance;
    
            if ($grandTotal > 0) {
    
                dd([
                    'member_id' => $member->id,
                    'member_name' => $member->user->name ?? 'N/A',
    
                    'missed_months' => $monthsOwed,
    
                    'total_contributions_owed' =>
                        $totalContributionOwed,
    
                    'total_contribution_penalties_due' =>
                        $totalContributionPenalty,
    
                    'loan_balance_owed' =>
                        $loanBalance,
    
                    'GRAND_TOTAL_DEBT_TO_PAY' =>
                        $grandTotal,
                ]);
            }
        }
    }

    protected function applyLoanPenalties(
        Society $society,
        $cycle,
        Carbon $month
    ): void {
    
        $loans = Loan::where('society_id', $society->id)
            ->where('cycle_id', $cycle->id)
            ->where('outstanding_balance', '>', 0)
            ->whereDate('due_date', '<=', $month->copy()->endOfMonth())
            ->get();
    
        foreach ($loans as $loan) {
    
            $alreadyPenalised = Transaction::where('loan_id', $loan->id)
                ->where('type', 'penalty')
                ->whereMonth('transaction_date', $month->month)
                ->whereYear('transaction_date', $month->year)
                ->exists();
    
            $existingDebt = $loan->outstanding_balance;
    
            /**
             * If penalty exists, show debt only
             */
            if ($alreadyPenalised) {
    
                dd([
                    'loan_id'           => $loan->id,
                    'member_id'         => $loan->member_id,
                    'month_checked'     => $month->format('F Y'),
                    'status'            => 'ALREADY PENALISED',
                    'current_total_owed'=> $existingDebt,
                    'penalty_added'     => 0,
                ]);
    
                continue;
            }
    
            /**
             * If no penalty exists, calculate preview
             */
            $penalty = round(
                $existingDebt *
                ($society->penalty_value / 100),
                2
            );
    
            $newDebt = $existingDebt + $penalty;
    
            dd([
                'loan_id'            => $loan->id,
                'member_id'          => $loan->member_id,
                'month_checked'      => $month->format('F Y'),
                'status'             => 'READY TO APPLY',
                'current_total_owed' => $existingDebt,
                'penalty_rate'       => $society->penalty_value . '%',
                'penalty_amount'     => $penalty,
                'new_total_owed'     => $newDebt,
            ]);
    
            /**
             * After confirming remove dd() and run:
             *
             * $loan->increment('penalty_amount', $penalty);
             * $loan->increment('outstanding_balance', $penalty);
             */
        }
    }
}