<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupDuplicateLoanPenalties extends Command
{
    protected $signature   = 'loans:cleanup-duplicate-penalties {--dry-run}';
    protected $description = 'Keep only the first penalty per loan, reverse and delete the rest';

    public function handle(): void
    {
        $dryRun = $this->option('dry-run');

        // Group all penalty logs by loan_id
        $loanIds = DB::table('reminder_logs')
            ->where('type', 'loan_penalty')
            ->distinct()
            ->pluck('loan_id');

        foreach ($loanIds as $loanId) {
            $loan = Loan::find($loanId);
            if (!$loan) {
                $this->warn("Loan #{$loanId} not found. Skipping.");
                continue;
            }

            $logs = $loan->reminderLogs()
                ->where('type', 'loan_penalty')
                ->orderBy('created_at')
                ->get();

            if ($logs->count() <= 1) {
                continue; // nothing to clean up
            }

            $keep   = $logs->first();
            $remove = $logs->slice(1); // everything after the first

            $this->info("Loan #{$loanId}: keeping penalty log #{$keep->id}, removing " . $remove->count() . " duplicate(s).");

            $totalReverse = 0.0;

            foreach ($remove as $log) {
                // Find the matching Transaction created same day for this loan
                $txn = Transaction::where('loan_id', $loanId)
                    ->where('type', 'penalty')
                    ->whereDate('transaction_date', $log->created_at->toDateString())
                    ->first();

                if ($txn) {
                    $totalReverse += (float) $txn->amount;
                    $this->line("  - Reversing Transaction #{$txn->id} (M{$txn->amount})");
                    if (!$dryRun) {
                        $txn->delete();
                    }
                } else {
                    $this->warn("  - No matching Transaction found for log #{$log->id} on {$log->created_at->toDateString()}");
                }

                if (!$dryRun) {
                    $log->delete();
                }
            }

            if ($totalReverse > 0) {
                $this->line("  - Reversing M{$totalReverse} from Loan #{$loanId} (penalty_amount & outstanding_balance)");
                if (!$dryRun) {
                    $loan->decrement('penalty_amount', $totalReverse);
                    $loan->decrement('outstanding_balance', $totalReverse);
                }
            }
        }

        $this->info($dryRun ? 'Dry run complete. No changes made.' : 'Cleanup complete.');
    }
}