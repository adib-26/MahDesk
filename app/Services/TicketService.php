<?php

namespace App\Services;

use App\Enums\MemberRole;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * Ticket write operations shared by the agent desk and the REST API, so both
 * apply the same permissions, SLA rules, automations and timeline events.
 */
class TicketService
{
    public function __construct(
        private SlaService $slaService,
        private AutomationEngine $automationEngine,
        private CustomerAccountLinker $customerAccountLinker,
        private TicketAttachmentService $attachments,
    ) {}

    /**
     * @param  array{subject: string, body: string, priority: string, channel: string, category: string, team_id?: int|null, assignee_id?: int|null, contact_id?: int|null, contact_name?: string|null, contact_email?: string|null}  $data
     * @param  list<\Illuminate\Http\UploadedFile>  $files  validated attachments for the first message
     */
    public function create(Workspace $workspace, User $actor, array $data, array $files = []): Ticket
    {
        $team = ! empty($data['team_id'])
            ? $workspace->teams()->findOrFail($data['team_id'])
            : $workspace->teams()->orderBy('id')->first();

        abort_unless($team !== null, 422, 'Create a team before creating tickets.');

        if ($workspace->roleOf($actor) === MemberRole::Manager) {
            abort_unless($team->hasMember($actor), 403, 'Managers can create tickets only for their teams.');
        }

        if (! empty($data['assignee_id'])) {
            $this->ensureStaffMember($workspace, (int) $data['assignee_id']);
        }

        $ticket = null;
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $ticket = DB::transaction(function () use ($data, $workspace, $actor, $team, $files) {
                    $contact = ! empty($data['contact_id'])
                        ? $workspace->contacts()->findOrFail($data['contact_id'])
                        : $workspace->contacts()->firstOrCreate(
                            ['email' => $data['contact_email']],
                            ['name' => $data['contact_name']],
                        );

                    $this->customerAccountLinker->link($workspace, $contact);

                    $ticket = $workspace->tickets()->create([
                        'number' => $workspace->nextTicketNumber(),
                        'subject' => $data['subject'],
                        'priority' => $data['priority'],
                        'channel' => $data['channel'],
                        'category' => $data['category'],
                        'contact_id' => $contact->id,
                        'team_id' => $team->id,
                        'assignee_id' => $data['assignee_id'] ?? null,
                    ]);

                    $message = $ticket->messages()->create([
                        'kind' => 'reply',
                        'is_from_contact' => true,
                        'body' => $data['body'],
                    ]);

                    $this->attachments->store($message, $files, $actor);

                    $ticket->logEvent("Ticket created by {$actor->name}", $actor->id);

                    return $ticket;
                });

                break;
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 5) {
                    throw new RuntimeException('Could not allocate a unique ticket number.', previous: $exception);
                }

                usleep(random_int(5, 50) * 1000);
            }
        }

        $this->slaService->apply($ticket);
        $this->automationEngine->run($ticket, AutomationEngine::EVENT_TICKET_CREATED);

        return $ticket->refresh();
    }

    /**
     * Apply a partial update. Only keys present in $data are changed.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Workspace $workspace, User $actor, Ticket $ticket, array $data): Ticket
    {
        $before = clone $ticket;

        if (array_key_exists('assignee_id', $data) || array_key_exists('team_id', $data)) {
            Gate::forUser($actor)->authorize('assign', $ticket);
        }

        if (array_key_exists('team_id', $data) && $data['team_id'] !== $ticket->team_id) {
            $team = $data['team_id'] === null ? null : $workspace->teams()->findOrFail($data['team_id']);

            if ($workspace->roleOf($actor) === MemberRole::Manager) {
                abort_unless($team?->hasMember($actor), 403, 'Managers can move tickets only to their teams.');
            }

            $ticket->team_id = $team?->id;
            $ticket->logEvent('Team changed to '.($team?->name ?? 'Unassigned').' by '.$actor->name, $actor->id);
        }

        if (array_key_exists('status', $data) && $data['status'] !== $ticket->status->value) {
            $status = TicketStatus::from($data['status']);
            $this->setStatus($ticket, $status);
            $ticket->logEvent("Status changed to {$status->value} by {$actor->name}", $actor->id);
        }

        $priorityChanged = false;
        if (array_key_exists('priority', $data) && $data['priority'] !== $ticket->priority->value) {
            $ticket->priority = $data['priority'];
            $priorityChanged = true;
            $ticket->logEvent("Priority changed to {$data['priority']} by {$actor->name}", $actor->id);
        }

        if (array_key_exists('category', $data) && $data['category'] !== $ticket->category->value) {
            $ticket->category = $data['category'];
            $ticket->logEvent("Category changed to {$data['category']} by {$actor->name}", $actor->id);
        }

        if (array_key_exists('assignee_id', $data) && $data['assignee_id'] !== $ticket->assignee_id) {
            if ($data['assignee_id'] !== null) {
                $member = $this->ensureStaffMember($workspace, (int) $data['assignee_id']);
                $ticket->assignee_id = $member->id;
                $ticket->logEvent("Assigned to {$member->name} by {$actor->name}", $actor->id);
            } else {
                $ticket->assignee_id = null;
                $ticket->logEvent("Unassigned by {$actor->name}", $actor->id);
            }
        }

        if (array_key_exists('subject', $data)) {
            $ticket->subject = $data['subject'];
        }

        $ticket->save();

        if (array_key_exists('tag_ids', $data)) {
            $ticket->tags()->sync($data['tag_ids']);
        }

        if ($priorityChanged) {
            $this->slaService->apply($ticket);
        }

        $this->automationEngine->run($ticket, AutomationEngine::EVENT_TICKET_UPDATED, $before);

        return $ticket->refresh();
    }

    /**
     * Add a reply or internal note, optionally changing the ticket status in
     * the same step. Replies written by the ticket's customer are stored as
     * customer messages and never count as the first staff response.
     *
     * @param  list<\Illuminate\Http\UploadedFile>  $files  validated attachments
     */
    public function addMessage(User $actor, Ticket $ticket, string $kind, string $body, ?string $status = null, bool $fromCustomer = false, array $files = []): TicketMessage
    {
        $before = clone $ticket;

        $message = DB::transaction(function () use ($actor, $ticket, $kind, $body, $fromCustomer, $files) {
            $message = $ticket->messages()->create([
                'author_id' => $actor->id,
                'kind' => $kind,
                'body' => $body,
                'is_from_contact' => $fromCustomer,
            ]);

            $this->attachments->store($message, $files, $actor);

            return $message;
        });

        if ($kind === 'reply' && ! $fromCustomer && ! $ticket->first_responded_at) {
            $ticket->first_responded_at = now();
        }

        if ($status !== null && $status !== '' && $status !== $ticket->status->value) {
            $newStatus = TicketStatus::from($status);
            $this->setStatus($ticket, $newStatus);
            $ticket->logEvent("Status changed to {$newStatus->value} by {$actor->name}", $actor->id);
        }

        $ticket->save();

        $this->automationEngine->run($ticket, AutomationEngine::EVENT_TICKET_UPDATED, $before);

        return $message;
    }

    private function setStatus(Ticket $ticket, TicketStatus $status): void
    {
        $ticket->status = $status;

        if ($status === TicketStatus::Resolved) {
            $ticket->resolved_at ??= now();
        } elseif ($status === TicketStatus::Closed) {
            $ticket->resolved_at ??= now();
            $ticket->closed_at ??= now();
        } else {
            $ticket->resolved_at = null;
            $ticket->closed_at = null;
        }
    }

    private function ensureStaffMember(Workspace $workspace, int $userId): User
    {
        $member = $workspace->members()
            ->wherePivotIn('role', MemberRole::staffValues())
            ->where('users.id', $userId)
            ->first();

        abort_unless($member !== null, 422, 'Assignee must be a support team member.');

        return $member;
    }
}
