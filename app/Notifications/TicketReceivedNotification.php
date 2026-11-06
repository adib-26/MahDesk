<?php

namespace App\Notifications;

use App\Support\MailText;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * Acknowledgement sent to the customer when their email opens a ticket. The
 * subject carries the ticket reference and the Message-ID is recorded on the
 * ticket, so a reply to this email threads back to the same ticket.
 */
class TicketReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public string $outboundMessageId,
        public ?string $inReplyTo = null,
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $workspace = $this->ticket->workspace;

        return (new MailMessage)
            ->from(config('mail.from.address'), $workspace->name)
            ->replyTo($workspace->replyToAddress(), $workspace->name)
            ->subject($this->ticket->emailSubject())
            ->greeting('Hello'.($this->ticket->contact?->name ? ' '.MailText::escape($this->ticket->contact->name) : '').',')
            ->line("We've received your request and opened ticket #{$this->ticket->number}.")
            ->line('A member of our support team will get back to you shortly.')
            ->line("To add more details, reply to this email and keep {$this->ticket->emailReference()} in the subject.")
            ->salutation('— '.MailText::escape($workspace->name))
            ->withSymfonyMessage(function (Email $message) {
                $headers = $message->getHeaders();
                $headers->addIdHeader('Message-ID', $this->outboundMessageId);
                if ($this->inReplyTo) {
                    $headers->addIdHeader('In-Reply-To', $this->inReplyTo);
                    $headers->addIdHeader('References', $this->inReplyTo);
                }
                // Tell other systems this is an automatic reply so they do not
                // auto-respond back and start a mail loop.
                $headers->addTextHeader('Auto-Submitted', 'auto-replied');
                $headers->addTextHeader('X-Auto-Response-Suppress', 'All');
            });
    }
}
