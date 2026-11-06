<?php

namespace App\Services\InboundEmail;

use App\Enums\TicketCategory;
use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Contact;
use App\Models\InboundEmail;
use App\Models\Ticket;
use App\Models\Workspace;
use App\Notifications\CustomerRepliedNotification;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketReceivedNotification;
use App\Services\AutomationEngine;
use App\Services\CustomerAccountLinker;
use App\Services\SlaService;
use App\Services\TicketAssigner;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Turns a stored inbound email into ticket activity:
 *
 *   resolve workspace → drop automated mail → thread or create ticket
 *   → SLA + automations → auto-assign → notify agent and customer
 */
class EmailTicketProcessor
{
    /** Statuses a customer reply moves back to Open. Closed tickets get a new follow-up ticket. */
    private const REOPEN_ON_REPLY = [TicketStatus::Pending, TicketStatus::OnHold, TicketStatus::Resolved];

    public function __construct(
        private InboundEmailParsers $parsers,
        private SlaService $slaService,
        private AutomationEngine $automationEngine,
        private TicketAssigner $assigner,
        private CustomerAccountLinker $customerAccountLinker,
    ) {}

    public function process(InboundEmail $record): void
    {
        // Claim the record atomically so a duplicate job cannot process it twice.
        $claimed = InboundEmail::query()
            ->whereKey($record->id)
            ->where('status', InboundEmail::STATUS_PENDING)
            ->update(['status' => InboundEmail::STATUS_PROCESSING, 'updated_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $record->refresh();

        try {
            $email = $this->parsers->for($record->provider)->parse($record->payload);
        } catch (InvalidArgumentException $exception) {
            $record->markAs(InboundEmail::STATUS_FAILED, $exception->getMessage());

            return;
        }

        try {
            DB::transaction(fn () => $this->handle($record, $email));
        } catch (Throwable $exception) {
            // Release the claim so the queue can retry; the job marks it failed
            // once retries are exhausted.
            $record->forceFill(['status' => InboundEmail::STATUS_PENDING])->save();

            throw $exception;
        }
    }

    private function handle(InboundEmail $record, ParsedEmail $email): void
    {
        $workspace = Workspace::forInboundRecipients($email->recipients);

        if (! $workspace) {
            $record->markAs(InboundEmail::STATUS_IGNORED, 'No workspace matches the recipient address.');

            return;
        }

        $ignoreReason = match (true) {
            $email->isAutomated() => 'Automated message (auto-reply, bounce, or mailing list).',
            $this->isFromHelpdesk($email) => 'Sent from a helpdesk address.',
            $this->isDuplicate($email, $workspace) => 'Message-ID already recorded on a ticket.',
            default => null,
        };

        if ($ignoreReason !== null) {
            $record->markAs(InboundEmail::STATUS_IGNORED, $ignoreReason, workspaceId: $workspace->id);

            return;
        }

        $existing = $this->findTicketForReply($workspace, $email);

        if ($existing && $existing->status !== TicketStatus::Closed) {
            $this->appendReply($existing, $email);
            $record->markAs(InboundEmail::STATUS_REPLIED, "Added to ticket #{$existing->number}.", $existing->id, $workspace->id);

            return;
        }

        $ticket = $this->createTicket($workspace, $email, $existing);
        $record->markAs(InboundEmail::STATUS_CREATED, "Created ticket #{$ticket->number}.", $ticket->id, $workspace->id);
    }

    /**
     * Match a reply to its ticket, first by Message-ID threading headers and
     * then by the [#number] subject reference. Either way the sender must be
     * the ticket's customer: ticket numbers are guessable, so a subject
     * reference alone must not let a stranger post into someone's ticket.
     */
    private function findTicketForReply(Workspace $workspace, ParsedEmail $email): ?Ticket
    {
        $ticket = null;

        if ($email->threadIds !== []) {
            $ticket = $workspace->tickets()
                ->whereHas('messages', fn ($messages) => $messages->whereIn('message_id', $email->threadIds))
                ->latest('id')
                ->first();
        }

        if (! $ticket && ($number = Ticket::numberFromEmailSubject($email->subject))) {
            $ticket = $workspace->tickets()->where('number', $number)->first();
        }

        if (! $ticket || Str::lower((string) $ticket->contact?->email) !== $email->fromEmail) {
            return null;
        }

        return $ticket;
    }

    private function appendReply(Ticket $ticket, ParsedEmail $email): void
    {
        $body = $email->visibleBody(isReply: true);
        $before = clone $ticket;

        $ticket->messages()->create([
            'author_id' => $ticket->contact->user_id,
            'kind' => 'reply',
            'is_from_contact' => true,
            'body' => $body,
            'message_id' => $email->messageId,
            'meta' => $this->meta($email),
        ]);

        if (in_array($ticket->status, self::REOPEN_ON_REPLY, true)) {
            $ticket->transitionStatus(TicketStatus::Open);
        } else {
            $ticket->touch();
        }

        $this->automationEngine->run($ticket, 'ticket_updated', $before);
        $ticket->refresh();

        $assignee = $ticket->assignee ?? $this->assigner->assignLeastBusy($ticket);
        $assignee?->notify(new CustomerRepliedNotification($ticket, $body));
    }

    private function createTicket(Workspace $workspace, ParsedEmail $email, ?Ticket $closedTicket): Ticket
    {
        // Matches how members and invitations provision the default team, so
        // inbound email is never dropped because a workspace has no team yet.
        $team = $workspace->teams()->orderBy('id')->first()
            ?? $workspace->teams()->create(['name' => 'General Support']);

        $contact = $this->contactFor($workspace, $email);
        $this->customerAccountLinker->link($workspace, $contact);

        $ticket = $this->createWithNextNumber($workspace, [
            'subject' => $this->cleanSubject($email->subject),
            'priority' => TicketPriority::Normal,
            'channel' => TicketChannel::Email,
            'category' => TicketCategory::General,
            'contact_id' => $contact->id,
            'team_id' => $team->id,
        ]);

        $body = $email->visibleBody(isReply: $closedTicket !== null);

        $ticket->messages()->create([
            'author_id' => $contact->user_id,
            'kind' => 'reply',
            'is_from_contact' => true,
            'body' => $body,
            'message_id' => $email->messageId,
            'meta' => $this->meta($email),
        ]);

        $ticket->logEvent("Ticket created from email by {$email->fromEmail}");

        if ($closedTicket) {
            $ticket->logEvent("Follow-up to closed ticket #{$closedTicket->number}", null, ['follow_up_of' => $closedTicket->id]);
        }

        $this->slaService->apply($ticket);
        $this->automationEngine->run($ticket, 'ticket_created');
        $ticket->refresh();

        // Automation rules win; otherwise fall back to the least busy agent.
        $assignee = $ticket->assignee ?? $this->assigner->assignLeastBusy($ticket);
        $assignee?->notify(new TicketAssignedNotification($ticket, $body));

        $this->acknowledge($ticket, $contact, $email);

        return $ticket;
    }

    private function acknowledge(Ticket $ticket, Contact $contact, ParsedEmail $email): void
    {
        $outboundId = Str::uuid()->toString().'@'.config('services.inbound_email.domain');

        // Recording the outbound Message-ID lets a reply thread by its
        // In-Reply-To header even if the customer edits the subject.
        $ticket->messages()->create([
            'kind' => 'event',
            'body' => "Acknowledgement emailed to {$contact->email}",
            'message_id' => $outboundId,
        ]);

        Notification::route('mail', [$contact->email => $contact->name])
            ->notify(new TicketReceivedNotification($ticket, $outboundId, $email->messageId));
    }

    private function contactFor(Workspace $workspace, ParsedEmail $email): Contact
    {
        $contact = $workspace->contacts()->whereRaw('lower(email) = ?', [$email->fromEmail])->first();

        return $contact ?? $workspace->contacts()->create([
            'email' => $email->fromEmail,
            'name' => Str::limit($email->fromName ?? Str::headline(Str::before($email->fromEmail, '@')), 100, ''),
        ]);
    }

    /**
     * Ticket numbers are allocated as max+1 without a lock, so a ticket
     * created in the desk at the same moment can collide. Retry inside a
     * savepoint so the surrounding transaction stays usable.
     */
    private function createWithNextNumber(Workspace $workspace, array $attributes): Ticket
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                return DB::transaction(fn () => $workspace->tickets()->create([
                    ...$attributes,
                    'number' => $workspace->nextTicketNumber(),
                ]));
            } catch (UniqueConstraintViolationException) {
                usleep(random_int(5, 50) * 1000);
            }
        }

        throw new RuntimeException('Could not allocate a ticket number.');
    }

    /**
     * Remove reply/forward prefixes and old ticket references, so a follow-up
     * to "[#9] Login issue" becomes "Login issue" rather than "[#10] Re: [#9] Login issue".
     */
    private function cleanSubject(string $subject): string
    {
        $clean = $subject;
        do {
            $previous = $clean;
            $clean = trim(preg_replace('/^(?:(?:re|fw|fwd|aw|sv)\s*:\s*|\[#\d+\]\s*)/i', '', $clean) ?? $clean);
        } while ($clean !== $previous);

        return Str::limit($clean !== '' ? $clean : '(no subject)', 200, '');
    }

    private function isFromHelpdesk(ParsedEmail $email): bool
    {
        // Any workspace routing or support address counts, but not the whole
        // inbound domain: on testmail.app customers' test inboxes share it.
        return $email->fromEmail === Str::lower((string) config('mail.from.address'))
            || Workspace::forInboundRecipients([$email->fromEmail]) !== null;
    }

    private function isDuplicate(ParsedEmail $email, Workspace $workspace): bool
    {
        return $email->messageId !== null
            && $workspace->tickets()
                ->whereHas('messages', fn ($messages) => $messages->where('message_id', $email->messageId))
                ->exists();
    }

    private function meta(ParsedEmail $email): array
    {
        return array_filter([
            'source' => 'email',
            'from' => $email->fromEmail,
            'to' => $email->recipients,
            'subject' => $email->subject,
            'attachments' => $email->attachments,
        ]);
    }
}
