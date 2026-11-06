<?php

namespace App\Notifications\Concerns;

use App\Models\Ticket;
use App\Models\User;

/**
 * Sends a ticket notification by email and, for registered users, also
 * stores it in the notifications table so the API can list it.
 */
trait StoresTicketNotification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['mail', 'database'] : ['mail'];
    }

    /** @return array<string, mixed> */
    protected function ticketPayload(Ticket $ticket, string $type, string $title, string $body): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'organization' => [
                'id' => $ticket->workspace->id,
                'slug' => $ticket->workspace->slug,
                'name' => $ticket->workspace->name,
            ],
            'ticket' => [
                'id' => $ticket->id,
                'number' => $ticket->number,
                'subject' => $ticket->subject,
            ],
        ];
    }
}
