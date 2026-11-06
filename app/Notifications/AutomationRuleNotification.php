<?php

namespace App\Notifications;

use App\Support\MailText;
use App\Models\AutomationRule;
use App\Models\Ticket;
use App\Notifications\Concerns\StoresTicketNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AutomationRuleNotification extends Notification implements ShouldQueue
{
    use Queueable, StoresTicketNotification;

    public function __construct(
        public Ticket $ticket,
        public AutomationRule $rule,
        public string $summary,
    ) {
        $this->afterCommit();
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return $this->ticketPayload($this->ticket, 'automation', "Workflow \"{$this->rule->name}\"", $this->summary);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->ticket;
        $greeting = isset($notifiable->name) ? 'Hi '.MailText::escape($notifiable->name).',' : 'Hello,';

        return (new MailMessage)
            ->subject("{$ticket->emailReference()} Workflow \"{$this->rule->name}\": {$ticket->subject}")
            ->greeting($greeting)
            ->line(MailText::escape($this->summary))
            ->line(sprintf(
                'Ticket #%d · Priority: %s · Category: %s · Status: %s',
                $ticket->number,
                strtoupper($ticket->priority->value),
                str_replace('_', ' ', $ticket->category->value),
                str_replace('_', ' ', $ticket->status->value),
            ))
            ->action('Open ticket', route('desk.tickets.show', ['workspace' => $ticket->workspace->slug, 'ticket' => $ticket->id]));
    }
}
