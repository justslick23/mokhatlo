<?php

namespace App\Mail;

use App\Models\Society;
use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoanRepaymentReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Society $society,
        public Loan    $loan,
        public int     $daysLeft
    ) {}

    public function envelope(): \Illuminate\Mail\Mailables\Envelope
    {
        return new \Illuminate\Mail\Mailables\Envelope(
            subject: "Loan Repayment Due in {$this->daysLeft} day(s) — {$this->society->name}",
        );
    }

    public function content(): \Illuminate\Mail\Mailables\Content
    {
        return new \Illuminate\Mail\Mailables\Content(
            view: 'emails.loan-repayment-reminder',
        );
    }
}