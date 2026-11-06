<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MemberRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexTicketMessagesRequest;
use App\Http\Requests\Api\V1\StoreTicketMessageRequest;
use App\Http\Resources\V1\TicketMessageResource;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\Workspace;
use App\Services\TicketAttachmentService;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketMessageController extends Controller
{
    public function __construct(private TicketService $tickets) {}

    public function index(IndexTicketMessagesRequest $request, Workspace $organization, Ticket $ticket): AnonymousResourceCollection
    {
        Gate::authorize('view', $ticket);

        $canViewNotes = $request->user()->can('viewInternalNotes', $ticket);

        $query = $ticket->messages()
            ->with(['author:id,name', 'attachments'])
            ->when(! $canViewNotes, fn ($q) => $q->where('kind', 'reply'))
            ->when($request->filter('kind'), fn ($q, $kind) => $q->where('kind', $kind));

        return TicketMessageResource::collection(
            $request->applySort($query)->paginate($request->perPage())->withQueryString(),
        );
    }

    public function store(StoreTicketMessageRequest $request, Workspace $organization, Ticket $ticket): JsonResponse
    {
        $fromCustomer = $organization->roleOf($request->user()) === MemberRole::Customer;

        $message = $this->tickets->addMessage(
            $request->user(),
            $ticket,
            $request->validated('kind', 'reply'),
            $request->validated('body'),
            $fromCustomer ? null : $request->validated('status'),
            $fromCustomer,
            $request->validated('attachments', []),
        );

        return TicketMessageResource::make($message->load(['author:id,name', 'attachments']))
            ->response()
            ->setStatusCode(201);
    }

    /** Download an attachment. Files on internal notes are limited to staff. */
    public function attachment(Request $request, Workspace $organization, Ticket $ticket, TicketAttachment $attachment, TicketAttachmentService $attachments): StreamedResponse
    {
        $attachments->authorizeDownload($request->user(), $attachment);

        return $attachments->download($attachment);
    }
}
