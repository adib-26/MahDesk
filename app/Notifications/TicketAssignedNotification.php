<?php

namespace App\Notifications;

use App\Support\MailText;
use App\Models\Ticket;
use App\Notifications\Concerns\StoresTicketNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class TicketAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable, StoresTicketNotification;

    public function __construct(public Ticket $ticket, public string $excerpt)
    {
        $this->afterCommit();
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return $this->ticketPayload(
            $this->ticket,
            'ticket_assigned',
            "Ticket #{$this->ticket->number} assigned to you",
            Str::limit($this->excerpt, 300),
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->ticket;

        return (new MailMessage)
            ->subject("{$ticket->emailReference()} Assigned to you: {$ticket->subject}")
            ->greeting('Hi '.MailText::escape($notifiable->name).',')
            ->line(MailText::escape($ticket->contact?->name).' <'.MailText::escape($ticket->contact?->email).'> emailed support and the ticket was assigned to you.')
            ->line('> '.MailText::escape(Str::limit($this->excerpt, 300)))
            ->action('Open ticket', route('desk.tickets.show', ['workspace' => $ticket->workspace->slug, 'ticket' => $ticket->id]));
    }
}
