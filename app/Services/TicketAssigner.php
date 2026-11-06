<?php

namespace App\Services;

use App\Enums\MemberRole;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

class TicketAssigner
{
    /**
     * Assign the ticket to the staff member of its team with the fewest open
     * tickets. Ties go to the member with the lowest user ID so the result is
     * deterministic. Returns null when the team has no staff members.
     */
    public function assignLeastBusy(Ticket $ticket): ?User
    {
        if ($ticket->assignee_id !== null || $ticket->team === null) {
            return null;
        }

        $staffIds = $ticket->workspace->members()
            ->wherePivotIn('role', MemberRole::staffValues())
            ->pluck('users.id');

        $candidates = $ticket->team->members()
            ->whereIn('users.id', $staffIds)
            ->orderBy('users.id')
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $openCounts = Ticket::query()
            ->where('workspace_id', $ticket->workspace_id)
            ->whereIn('assignee_id', $candidates->modelKeys())
            ->whereNotIn('status', [TicketStatus::Resolved->value, TicketStatus::Closed->value])
            ->selectRaw('assignee_id, count(*) as total')
            ->groupBy('assignee_id')
            ->pluck('total', 'assignee_id');

        /** @var User $agent */
        $agent = $candidates->sortBy(fn (User $user) => [(int) ($openCounts[$user->id] ?? 0), $user->id])->first();
        $openTickets = (int) ($openCounts[$agent->id] ?? 0);

        $ticket->assignee_id = $agent->id;
        $ticket->save();
        $ticket->setRelation('assignee', $agent);
        $ticket->logEvent(
            "Auto-assigned to {$agent->name} ({$openTickets} open ".str('ticket')->plural($openTickets)." in {$ticket->team->name})",
            null,
            ['assignment' => 'least_open_tickets'],
        );

        return $agent;
    }
}
