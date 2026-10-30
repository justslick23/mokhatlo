<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SocietyInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $societyName,
        public string $inviterName,
        public string $token,
        public string $expiresAt
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(
                'Invitation to join ' . $this->societyName
            )
            ->greeting('You have been invited!')
            ->line(
                $this->inviterName .
                ' has invited you to join ' .
                $this->societyName .
                ' on Mokhatlo.'
            )
            ->line(
                'Mokhatlo helps members manage savings, ' .
                'loans, contributions and financial records.'
            )
            ->action(
                'Accept Invitation',
                route('invitations.show', [
                    'token' => $this->token,
                ])
            )
            ->line(
                'This invitation expires on ' .
                $this->expiresAt . '.'
            )
            ->line(
                'If you were not expecting this invitation, ' .
                'you can ignore this email.'
            );
    }
}
