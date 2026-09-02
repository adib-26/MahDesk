<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomerTicketController extends Controller
{
    /**
     * List the authenticated customer's tickets, grouped by workspace.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $tickets = Ticket::query()
            ->whereHas('contact', fn (Builder $query) => $query->where('user_id', $user->getKey()))
            ->with('workspace:id,name')
            ->orderByDesc('updated_at')
            ->get(['id', 'workspace_id', 'number', 'subject', 'status', 'priority', 'created_at', 'updated_at']);

        $ticketGroups = $tickets
            ->groupBy('workspace_id')
            ->map(function ($workspaceTickets) {
                $workspace = $workspaceTickets->first()->workspace;

                return [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'tickets' => $workspaceTickets->map(fn (Ticket $ticket) => $this->ticketSummary($ticket))->values(),
                ];
            })
            ->sortBy('name')
            ->values();

        return Inertia::render('customer/tickets/index', [
            'ticketGroups' => $ticketGroups,
        ]);
    }

    /**
     * Show a customer-safe view of one ticket and its public replies.
     */
    public function show(Ticket $ticket): Response
    {
        Gate::authorize('view', $ticket);

        $ticket->load([
            'workspace:id,name',
            'messages' => fn ($query) => $query
                ->where('kind', 'reply')
                ->orderBy('created_at')
                ->select(['id', 'ticket_id', 'is_from_contact', 'body', 'created_at']),
        ]);

        return Inertia::render('customer/tickets/show', [
            'ticket' => [
                ...$this->ticketSummary($ticket),
                'created_at' => $ticket->created_at?->toIso8601String(),
                'workspace' => [
                    'name' => $ticket->workspace->name,
                ],
                'messages' => $ticket->messages->map(fn ($message) => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'is_from_contact' => $message->is_from_contact,
                    'created_at' => $message->created_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    /**
     * Add a public customer reply without accepting staff-only message fields.
     */
    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('reply', $ticket);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $ticket->messages()->create([
            'author_id' => $request->user()->getKey(),
            'kind' => 'reply',
            'is_from_contact' => true,
            'body' => $validated['body'],
        ]);

        return back()->with('success', 'Reply sent.');
    }

    /**
     * Serialize only metadata that is appropriate for the customer portal.
     */
    private function ticketSummary(Ticket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'number' => $ticket->number,
            'subject' => $ticket->subject,
            'status' => $ticket->status->value,
            'priority' => $ticket->priority->value,
            'updated_at' => $ticket->updated_at?->toIso8601String(),
        ];
    }
}
