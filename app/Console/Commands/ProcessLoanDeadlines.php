<?php

namespace App\Console\Commands;

use App\Models\Society;
use App\Models\Loan;
use App\Models\Transaction;
use App\Mail\LoanRepaymentReminder;
use App\Mail\LoanPenaltyApplied;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class ProcessLoanDeadlines extends Command
{
    protected $signature   = 'loans:process-deadlines';
    protected $description = 'Send loan repayment reminders and apply penalties on overdue loans';

    const REMINDER_DAYS_BEFORE = 3; // matches your isDueSoon(3) default

    public function handle(): void
    {
        Society::with(['activeCycle'])
            ->where('status', 'active')
            ->each(function (Society $society) {

                $cycle = $society->activeCycle;

                if (!$cycle) {
                    $this->warn("  [{$society->name}] No active cycle. Skipping.");
                    return;
                }

                // All active/overdue loans for this society in the current cycle
                $loans = Loan::with(['member.user'])
                    ->where('society_id', $society->id)
                    ->where('cycle_id', $cycle->id)
                    ->whereIn('status', ['active', 'overdue'])
                    ->get();

                if ($loans->isEmpty()) {
                    $this->info("  [{$society->name}] No active loans. Skipping.");
                    return;
                }

                foreach ($loans as $loan) {
                    // ── Due soon → send reminder ───────────────────────
                    if ($loan->isDueSoon(self::REMINDER_DAYS_BEFORE)) {
                        $this->sendReminder($society, $loan);
                    }

                    // ── Past due → apply penalty ───────────────────────
                    if ($loan->isDue()) {
                        $this->applyPenalty($society, $cycle, $loan);
                    }
                }
            });

        $this->info('Done.');
    }

    // ── Reminder email ────────────────────────────────────────────
    protected function sendReminder(Society $society, Loan $loan): void
    {
        $daysLeft = (int) now()->diffInDays($loan->due_date, false);

        // Avoid sending multiple reminders on the same day
        $alreadyReminded = $loan->reminderLogs()
            ->whereDate('created_at', now()->toDateString())
            ->where('type', 'loan_due_soon')
            ->exists();

        if ($alreadyReminded) {
            $this->warn("  Reminder already sent today → Loan #{$loan->id}. Skipping.");
            return;
        }

        Mail::to($loan->member->user->email)
            ->send(new LoanRepaymentReminder($society, $loan, $daysLeft));

        // Log the reminder so we don't double-send
        $loan->reminderLogs()->create([
            'society_id' => $society->id,
            'member_id'  => $loan->member_id,
            'type'       => 'loan_due_soon',
            'message'    => "Loan #{$loan->id} due in {$daysLeft} day(s). Outstanding: M{$loan->outstanding_balance}",
        ]);

        $this->info("  Reminder → {$loan->member->user->email} (Loan #{$loan->id}, {$daysLeft} days left)");
    }

    // ── Apply penalty using Loan::applyPenalty() ──────────────────
    protected function applyPenalty(Society $society, $cycle, Loan $loan): void
    {
        // Avoid double-penalising on the same day
        $alreadyPenalised = $loan->reminderLogs()
            ->whereDate('created_at', now()->toDateString())
            ->where('type', 'loan_penalty')
            ->exists();

        if ($alreadyPenalised) {
            $this->warn("  Penalty already applied today → Loan #{$loan->id}. Skipping.");
            return;
        }

        // Calculate penalty amount BEFORE applying
        // (mirrors Loan::applyPenalty() logic so we can record the Transaction)
        if ($society->penalty_type === 'fixed') {
            $penaltyAmount = (float) $society->penalty_value;
        } else {
            $penaltyAmount = round(
                $loan->outstanding_balance * ($society->penalty_value / 100),
                2
            );
        }

        // Use the existing applyPenalty() on the Loan model
        // which updates penalty_amount, outstanding_balance and sets status = 'overdue'
        $loan->applyPenalty();

        // Record as a Transaction for financial tracking
        Transaction::create([
            'society_id'       => $society->id,
            'member_id'        => $loan->member_id,
            'cycle_id'         => $cycle->id,
            'loan_id'          => $loan->id,
            'type'             => 'penalty',
            'amount'           => $penaltyAmount,
            'transaction_date' => now()->toDateString(),
            'notes'            => $society->penalty_type === 'fixed'
                ? "Auto-penalty: fixed M{$penaltyAmount} on overdue Loan #{$loan->id}."
                : "Auto-penalty: {$society->penalty_value}% of outstanding balance (M{$loan->outstanding_balance}) on Loan #{$loan->id}.",
        ]);

        // Log it so we don't double-penalise today
        $loan->reminderLogs()->create([
            'society_id' => $society->id,
            'member_id'  => $loan->member_id,
            'type'       => 'loan_penalty',
            'message'    => "Penalty M{$penaltyAmount} applied on Loan #{$loan->id}. New balance: M{$loan->outstanding_balance}",
        ]);

        Mail::to($loan->member->user->email)
            ->send(new LoanPenaltyApplied($society, $loan, $penaltyAmount));

        $this->info("  Penalty applied → {$loan->member->user->email} (Loan #{$loan->id}, M{$penaltyAmount})");
    }
}