<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\Workspace;
use App\Services\AutomationEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TicketMessageController extends Controller
{
    public function __construct(private AutomationEngine $automationEngine)
    {
    }

    public function store(Request $request, Workspace $workspace, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->workspace_id === $workspace->id, 404);

        $kind = $request->input('kind', 'reply');
        Gate::authorize($kind === 'note' ? 'addInternalNote' : 'reply', $ticket);

        $validated = $request->validate([
            'kind' => ['required', Rule::in(['reply', 'note'])],
            'body' => ['required', 'string', 'max:10000'],
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
        ]);

        $ticket->messages()->create([
            'author_id' => $request->user()->id,
            'kind' => $validated['kind'],
            'body' => $validated['body'],
        ]);

        if ($validated['kind'] === 'reply' && ! $ticket->first_responded_at) {
            $ticket->first_responded_at = now();
        }

        if (! empty($validated['status']) && $validated['status'] !== $ticket->status->value) {
            $newStatus = TicketStatus::from($validated['status']);
            $ticket->status = $newStatus;

            if ($newStatus === TicketStatus::Resolved) {
                $ticket->resolved_at ??= now();
            } elseif ($newStatus === TicketStatus::Closed) {
                $ticket->resolved_at ??= now();
                $ticket->closed_at ??= now();
            } else {
                $ticket->resolved_at = null;
                $ticket->closed_at = null;
            }

            $ticket->logEvent("Status changed to {$newStatus->value} by {$request->user()->name}", $request->user()->id);
        }

        $ticket->save();

        $this->automationEngine->run($ticket, 'ticket_updated');

        return back()->with('success', $validated['kind'] === 'note' ? 'Internal note added.' : 'Reply sent.');
    }
}
