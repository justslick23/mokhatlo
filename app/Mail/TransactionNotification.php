<?php

namespace App\Mail;

use App\Models\Member;
use App\Models\Society;
use App\Models\Transaction;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;

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

        Log::info('TransactionNotification mail class initialized', [
            'society_id'          => $society->id,
            'transaction_id'      => $transaction->id,
            'member_id'           => $member->id,
            'cycle_id'            => $cycleId,
            'total_contributions' => $this->totalContributions,
            'total_loans'         => $this->totalLoans,
            'cycle_balance'       => $this->cycleBalance,
        ]);
    }

    public function build()
    {
        Log::info('Building TransactionNotification email', [
            'transaction_id' => $this->transaction->id,
            'member_id'      => $this->member->id,
            'subject'        => "New transaction in {$this->society->name}",
        ]);

        return $this->subject("New transaction in {$this->society->name}")
                    ->view('emails.transaction-notification');
    }
}