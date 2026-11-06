<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\Workspace;
use App\Services\TicketService;
use App\Support\AttachmentRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TicketMessageController extends Controller
{
    public function __construct(private TicketService $tickets)
    {
    }

    public function store(Request $request, Workspace $workspace, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->workspace_id === $workspace->id, 404);

        $kind = $request->input('kind', 'reply');
        Gate::authorize($kind === 'note' ? 'addInternalNote' : 'reply', $ticket);

        $validator = Validator::make($request->all(), [
            'kind' => ['required', Rule::in(['reply', 'note'])],
            'body' => ['required', 'string', 'max:10000'],
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            ...AttachmentRules::rules(),
        ], AttachmentRules::messages());
        $validator->after(fn ($v) => AttachmentRules::checkTotalSize($v, $request->file('attachments')));
        $validated = $validator->validate();

        $this->tickets->addMessage(
            $request->user(),
            $ticket,
            $validated['kind'],
            $validated['body'],
            $validated['status'] ?? null,
            files: $validated['attachments'] ?? [],
        );

        return back()->with('success', $validated['kind'] === 'note' ? 'Internal note added.' : 'Reply sent.');
    }
}
