<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\Transaction;
use Illuminate\Console\Command;

class BackfillLoanInterestPaid extends Command
{
    protected $signature   = 'loans:backfill-interest';
    protected $description = 'Backfill interest_paid on loans and create loan_interest transactions for existing repayments';

    public function handle(): void
    {
        $loans = Loan::with(['transactions' => function ($q) {
                $q->where('type', 'loan_repayment')->orderBy('transaction_date')->orderBy('created_at');
            }])
            ->whereHas('transactions', fn($q) => $q->where('type', 'loan_repayment'))
            ->get();

        $this->info("Processing {$loans->count()} loans...");

        foreach ($loans as $loan) {
            $interestPaid    = 0;
            $interestOwed    = (float) $loan->interest;
            $penaltyTracked  = 0;

            foreach ($loan->transactions as $repayment) {
                $remaining = (float) $repayment->amount;

                // 1. Penalty portion
                $penaltyOwed    = max(0, (float) $loan->penalty_amount - $penaltyTracked);
                $penaltyPortion = min($remaining, $penaltyOwed);
                $penaltyTracked += $penaltyPortion;
                $remaining      -= $penaltyPortion;

                // 2. Interest portion
                $interestRemaining = max(0, $interestOwed - $interestPaid);
                $interestPortion   = min($remaining, $interestRemaining);
                $remaining        -= $interestPortion;
                $interestPaid     += $interestPortion;

                // 3. Create loan_interest transaction if not already exists
                if ($interestPortion > 0) {
                    $alreadyExists = Transaction::where('loan_id', $loan->id)
                        ->where('type', 'loan_interest')
                        ->where('transaction_date', $repayment->transaction_date)
                        ->exists();

                    if (!$alreadyExists) {
                        Transaction::create([
                            'society_id'       => $loan->society_id,
                            'cycle_id'         => $loan->cycle_id,
                            'member_id'        => $loan->member_id,
                            'loan_id'          => $loan->id,
                            'type'             => 'loan_interest',
                            'amount'           => $interestPortion,
                            'transaction_date' => $repayment->transaction_date,
                            'notes'            => "Backfilled interest portion of repayment on Loan #{$loan->id}",
                        ]);

                        $this->info("  Loan #{$loan->id} → interest M{$interestPortion} on {$repayment->transaction_date}");
                    }
                }
            }

            // Update interest_paid on the loan
            $loan->interest_paid = $interestPaid;
            $loan->save();

            $this->info("  Loan #{$loan->id} → interest_paid set to M{$interestPaid}");
        }

        $this->info('Done.');
    }
}