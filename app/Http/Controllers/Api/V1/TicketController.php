<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexTicketsRequest;
use App\Http\Requests\Api\V1\StoreTicketRequest;
use App\Http\Requests\Api\V1\UpdateTicketRequest;
use App\Http\Resources\V1\TicketResource;
use App\Models\Ticket;
use App\Models\Workspace;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    private const LIST_RELATIONS = ['contact', 'assignee:id,name', 'team:id,name', 'tags:id,name,color', 'slaPolicy:id,name'];

    public function __construct(private TicketService $tickets) {}

    public function index(IndexTicketsRequest $request, Workspace $organization): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Ticket::class, $organization]);

        $user = $request->user();

        $query = Ticket::query()
            ->visibleTo($user, $organization)
            ->with(self::LIST_RELATIONS)
            ->when($request->filter('status'), fn ($q, $v) => $q->whereIn('status', $v))
            ->when($request->filter('priority'), fn ($q, $v) => $q->whereIn('priority', $v))
            ->when($request->filter('category'), fn ($q, $v) => $q->whereIn('category', $v))
            ->when($request->filter('channel'), fn ($q, $v) => $q->whereIn('channel', $v))
            ->when($request->filter('assignee'), fn ($q, $assignee) => match ($assignee) {
                'me' => $q->where('assignee_id', $user->id),
                'unassigned' => $q->whereNull('assignee_id'),
                default => $q->where('assignee_id', (int) $assignee),
            })
            ->when($request->filter('team_id'), fn ($q, $v) => $q->where('team_id', $v))
            ->when($request->filter('customer_id'), fn ($q, $v) => $q->where('contact_id', $v))
            ->when($request->filter('tag'), fn ($q, $tag) => $q->whereHas('tags', fn ($t) => ctype_digit((string) $tag)
                ? $t->where('tags.id', (int) $tag)
                : $t->where('tags.name', $tag)))
            ->when($request->has('sla_breached'), fn ($q) => $request->boolean('sla_breached')
                ? $q->where(fn ($w) => $w->whereColumn('resolved_at', '>', 'resolution_due_at')
                    ->orWhere(fn ($open) => $open->whereNull('resolved_at')->where('resolution_due_at', '<', now())))
                : $q->where(fn ($w) => $w->whereNull('resolution_due_at')
                    ->orWhereColumn('resolved_at', '<=', 'resolution_due_at')
                    ->orWhere(fn ($open) => $open->whereNull('resolved_at')->where('resolution_due_at', '>=', now()))))
            ->when($request->filter('created_from'), fn ($q, $date) => $q->where('created_at', '>=', $date))
            ->when($request->filter('created_to'), fn ($q, $date) => $q->where('created_at', '<=', $date))
            ->when($request->filter('q'), fn ($q, $search) => $q->where(fn ($w) => $w
                ->whereContains('subject', $search)
                ->orWhere('number', ltrim($search, '#'))
                ->orWhereHas('contact', fn ($c) => $c->whereContains(['name', 'email'], $search))));

        return TicketResource::collection(
            $request->applySort($query)->paginate($request->perPage())->withQueryString(),
        );
    }

    public function store(StoreTicketRequest $request, Workspace $organization): JsonResponse
    {
        $ticket = $this->tickets->create($organization, $request->user(), $request->ticketData(), $request->validated('attachments', []));

        return TicketResource::make($ticket->load(self::LIST_RELATIONS))
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('api.v1.tickets.show', [$organization->slug, $ticket->number]));
    }

    /** A ticket with its conversation. Internal notes appear only for staff. */
    public function show(Request $request, Workspace $organization, Ticket $ticket): TicketResource
    {
        Gate::authorize('view', $ticket);

        $canViewNotes = $request->user()->can('viewInternalNotes', $ticket);

        return TicketResource::make($ticket->load([
            ...self::LIST_RELATIONS,
            'messages' => fn ($q) => $q
                ->when(! $canViewNotes, fn ($m) => $m->where('kind', 'reply'))
                ->with(['author:id,name', 'attachments'])
                ->orderBy('created_at')
                ->orderBy('id'),
        ]));
    }

    public function update(UpdateTicketRequest $request, Workspace $organization, Ticket $ticket): TicketResource
    {
        $ticket = $this->tickets->update($organization, $request->user(), $ticket, $request->validated());

        return TicketResource::make($ticket->load(self::LIST_RELATIONS));
    }

    public function destroy(Workspace $organization, Ticket $ticket): JsonResponse
    {
        Gate::authorize('delete', $ticket);

        $ticket->delete();

        return response()->json(null, 204);
    }
}
