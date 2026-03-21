<?php

namespace App\Mail;

use App\Models\Society;
use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PenaltyApplied extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Society     $society,
        public Member      $member,
        public Transaction $penalty
    ) {}

    public function envelope(): \Illuminate\Mail\Mailables\Envelope
    {
        return new \Illuminate\Mail\Mailables\Envelope(
            subject: "Penalty Applied — {$this->society->name}",
        );
    }

    public function content(): \Illuminate\Mail\Mailables\Content
    {
        return new \Illuminate\Mail\Mailables\Content(
            view: 'emails.penalty-applied',
        );
    }
}