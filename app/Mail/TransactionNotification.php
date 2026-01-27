<?php

namespace App\Mail;

use App\Models\Member;
use App\Models\Society;
use App\Models\Transaction;
use App\Models\Loan;
use Illuminate\Mail\Mailable;

class TransactionNotification extends Mailable
{
    public $loan;
    public $cycleBalance;
    public $totalContributions;
    public $totalLoans;

    public function __construct(
        public Society $society,
        public Transaction $transaction,
        public Member $member,
    ) {
        $this->loan = $transaction->loan;
        
        // Calculate cycle totals
        $cycleId = $transaction->cycle_id;
        $this->totalContributions = $society->transactions()
            ->where('type', 'contribution')
            ->where('cycle_id', $cycleId)
            ->sum('amount');
        
        $this->totalLoans = $society->transactions()
            ->where('type', 'loan_disbursement')
            ->where('cycle_id', $cycleId)
            ->sum('amount');
        
        $this->cycleBalance = $this->totalContributions - $this->totalLoans;
    }

    public function build()
    {
        return $this->subject("New transaction in {$this->society->name}")
                    ->view('emails.transaction-notification');
    }
}