<?php

namespace App\Mail;

use App\Models\Society;
use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoanPenaltyApplied extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Society $society,
        public Loan    $loan,
        public float   $penaltyAmount
    ) {}

    public function envelope(): \Illuminate\Mail\Mailables\Envelope
    {
        return new \Illuminate\Mail\Mailables\Envelope(
            subject: "Loan Penalty Applied — {$this->society->name}",
        );
    }

    public function content(): \Illuminate\Mail\Mailables\Content
    {
        return new \Illuminate\Mail\Mailables\Content(
            view: 'emails.loan-penalty-applied',
        );
    }
}