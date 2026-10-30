<?php

namespace App\Mail;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;

final class MokhatloStatementMail extends Mailable
{
    public function __construct(
        private readonly string $mailSubject,
        public readonly array $notice,
        private readonly ?array $report = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.mokhatlo-notice', with: [
            'notice' => $this->notice,
        ]);
    }

    public function attachments(): array
    {
        if ($this->report === null) {
            return [];
        }
        $data = $this->report;
        $kind = $data['kind'];
        $period = $data['period'];
        $template = $kind === 'member' ? 'pdf.mokhatlo-member' : 'pdf.mokhatlo-society';
        // Attachment is generated only for the intended recipient; never a public URL.
        $filename = 'mokhatlo-' . $kind . '-statement-' . $period . '.pdf';
        return [
            Attachment::fromData(
                static fn (): string => Pdf::loadView($template, ['report' => $data])
                    ->setPaper('a4')->output(),
                $filename
            )->withMime('application/pdf'),
        ];
    }
}
