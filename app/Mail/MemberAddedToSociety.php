<?php

namespace App\Mail;

use App\Models\Member;
use App\Models\Society;
use App\Models\User;
use Illuminate\Mail\Mailable;

class MemberAddedToSociety extends Mailable
{
    public function __construct(
        public User $user,
        public Society $society,
        public Member $member,
    ) {
    }

    public function build()
    {
        return $this->subject("Welcome to {$this->society->name}")
                    ->view('emails.member-added-to-society');
    }
}