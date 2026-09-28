<?php

namespace App\Notifications;

use App\Models\StaffInvitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffInvitationNotification extends Notification
{
    public function __construct(public StaffInvitation $invitation, private string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Join '.$this->invitation->company->name.' on Wastee')
            ->line($this->invitation->inviter->name.' invited you to '.$this->invitation->company->name.'.')
            ->line('Invited role: '.$this->invitation->role_name)
            ->line('This single-use invitation expires '.$this->invitation->expires_at->format('d M Y H:i T').'.')
            ->action('Review invitation', route('staff-invitations.open', ['invitation' => $this->invitation->uuid, 'token' => $this->token]))
            ->line('If you already have a Wastee account, sign in with the invited email address.');
    }
}
