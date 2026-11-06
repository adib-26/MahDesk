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

class CustomerRepliedNotification extends Notification implements ShouldQueue
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
            'customer_replied',
            "New reply on #{$this->ticket->number}",
            Str::limit($this->excerpt, 300),
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->ticket;

        return (new MailMessage)
            ->subject("{$ticket->emailReference()} New reply from {$ticket->contact?->name}")
            ->greeting('Hi '.MailText::escape($notifiable->name).',')
            ->line(MailText::escape($ticket->contact?->name)." replied to ticket #{$ticket->number}: ".MailText::escape($ticket->subject))
            ->line('> '.MailText::escape(Str::limit($this->excerpt, 300)))
            ->action('Open ticket', route('desk.tickets.show', ['workspace' => $ticket->workspace->slug, 'ticket' => $ticket->id]));
    }
}
