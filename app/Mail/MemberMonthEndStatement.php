<?php

namespace App\Mail;

use App\Models\Society;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberMonthEndStatement extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Society $society,
        public Member  $member,
        public Carbon  $period,
        public array   $memberData,    // ← added
        public string  $pdfContent,
        public string  $filename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Statement — {$this->society->name} {$this->period->format('F Y')}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.member-month-end-statement',
            with: [
                'society'    => $this->society,
                'member'     => $this->member,
                'period'     => $this->period,
                'memberData' => $this->memberData,  // ← passed to Blade
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn() => $this->pdfContent, $this->filename)
                ->withMime('application/pdf'),
        ];
    }
}