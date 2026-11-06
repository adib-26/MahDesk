<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\Workspace;
use App\Services\TicketAttachmentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Attachment downloads for the agent desk and the customer portal. Files are
 * on a private disk, so this is the only way to reach them.
 */
class TicketAttachmentController extends Controller
{
    public function __construct(private TicketAttachmentService $attachments) {}

    public function desk(Request $request, Workspace $workspace, Ticket $ticket, TicketAttachment $attachment): StreamedResponse
    {
        abort_unless($ticket->workspace_id === $workspace->id && $attachment->ticket_id === $ticket->id, 404);

        $this->attachments->authorizeDownload($request->user(), $attachment);

        return $this->attachments->download($attachment);
    }

    public function customer(Request $request, Ticket $ticket, TicketAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->ticket_id === $ticket->id, 404);

        $this->attachments->authorizeDownload($request->user(), $attachment);

        return $this->attachments->download($attachment);
    }
}
