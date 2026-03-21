<?php

namespace App\Mail;

use App\Models\Society;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SocietyMonthEndReport extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Society $society,
        public Carbon  $period,
        public array   $summary,       // ← added
        public string  $pdfContent,
        public string  $filename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->society->name} — Month-End Report {$this->period->format('F Y')}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.society-month-end-report',
            with: [
                'society' => $this->society,
                'period'  => $this->period,
                'summary' => $this->summary,   // ← passed to Blade
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