<?php

namespace App\Mail;

use App\Models\Member;
use App\Models\Society;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewMemberJoinedSociety extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Society $society,
        public Member $newMember
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Member Joined {$this->society->name} - Mokhatlo",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-member-joined',
            with: [
                'user' => $this->user,
                'society' => $this->society,
                'newMember' => $this->newMember,
            ],
        );
    }
}