<?php

namespace App\Notifications;

use App\Models\WorkspaceInvitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvitationNotification extends Notification
{
    public function __construct(private WorkspaceInvitation $invitation)
    {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $workspace = $this->invitation->workspace;
        $role = $this->invitation->role()->label();

        return (new MailMessage)
            ->subject("You're invited to {$workspace->name}")
            ->greeting('Hello'.($this->invitation->name ? " {$this->invitation->name}" : '').',')
            ->line("You have been invited to join {$workspace->name} as {$role}.")
            ->action('Accept invitation', route('invitations.show', $this->invitation->token))
            ->line('This invitation expires in 7 days. If you were not expecting it, you can ignore this email.');
    }
}
