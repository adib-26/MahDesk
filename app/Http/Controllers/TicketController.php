<?php

namespace App\Http\Controllers;

use App\Enums\MemberRole;
use App\Enums\TicketCategory;
use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\Workspace;
use App\Services\TicketService;
use App\Support\AttachmentLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function __construct(private TicketService $tickets)
    {
    }

    public function index(Request $request, Workspace $workspace): Response
    {
        Gate::authorize('viewAny', [Ticket::class, $workspace]);

        $filters = [
            'status' => $request->query('status', 'all'),
            'priority' => $request->query('priority'),
            'category' => $request->query('category'),
            'assignee' => $request->query('assignee'),
            'tag' => $request->query('tag'),
            'q' => $request->query('q'),
            'view' => $request->query('view'),
        ];

        $visibleTickets = Ticket::query()->visibleTo($request->user(), $workspace);

        $tickets = (clone $visibleTickets)
            ->with(['contact:id,name,email,company', 'assignee:id,name', 'tags:id,name,color'])
            ->when(in_array($filters['view'], ['active', 'resolved', 'breached'], true), function ($q) use ($filters) {
                $since = now()->subDays(29)->startOfDay();

                match ($filters['view']) {
                    'active' => $q->whereNotIn('status', [TicketStatus::Resolved->value, TicketStatus::Closed->value]),
                    'resolved' => $q->where('resolved_at', '>=', $since),
                    'breached' => $q
                        ->where(function ($tickets) use ($since) {
                            $tickets->whereNotIn('status', [TicketStatus::Resolved->value, TicketStatus::Closed->value])
                                ->orWhere('created_at', '>=', $since)
                                ->orWhere('resolved_at', '>=', $since);
                        })
                        ->where(function ($tickets) {
                            $tickets->where(function ($breach) {
                                $breach->whereNotNull('first_response_due_at')
                                    ->where(function ($due) {
                                        $due->whereColumn('first_responded_at', '>', 'first_response_due_at')
                                            ->orWhere(fn ($open) => $open->whereNull('first_responded_at')->where('first_response_due_at', '<', now()));
                                    });
                            })->orWhere(function ($breach) {
                                $breach->whereNotNull('resolution_due_at')
                                    ->where(function ($due) {
                                        $due->whereColumn('resolved_at', '>', 'resolution_due_at')
                                            ->orWhere(fn ($open) => $open->whereNull('resolved_at')->where('resolution_due_at', '<', now()));
                                    });
                            });
                        }),
                };
            })
            ->when($filters['status'] !== 'all', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['priority'], fn ($q, $priority) => $q->where('priority', $priority))
            ->when($filters['category'], fn ($q, $category) => $q->where('category', $category))
            ->when($filters['assignee'], function ($q, $assignee) use ($request) {
                match ($assignee) {
                    'me' => $q->where('assignee_id', $request->user()->id),
                    'unassigned' => $q->whereNull('assignee_id'),
                    default => $q->where('assignee_id', $assignee),
                };
            })
            ->when($filters['tag'], fn ($q, $tag) => $q->whereHas('tags', fn ($t) => $t->where('tags.id', $tag)))
            ->when($filters['q'], function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->whereContains('subject', $search)
                        ->orWhere('number', ltrim($search, '#'))
                        ->orWhereHas('contact', fn ($c) => $c->whereContains(['name', 'email'], $search));
                });
            })
            ->orderByDesc('updated_at')
            ->paginate(12)
            ->withQueryString();

        $statusCounts = (clone $visibleTickets)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $staffRoles = [
            MemberRole::Owner->value,
            MemberRole::Admin->value,
            MemberRole::Manager->value,
            MemberRole::Agent->value,
        ];
        $canCreateTickets = Gate::allows('create', [Ticket::class, $workspace]);

        return Inertia::render('desk/tickets/index', [
            'tickets' => $tickets,
            'filters' => $filters,
            'statusCounts' => $statusCounts,
            'agents' => $workspace->members()->wherePivotIn('role', $staffRoles)->orderBy('name')->get(['users.id', 'users.name']),
            'tags' => $workspace->tags()->orderBy('name')->get(['id', 'name', 'color']),
            'contacts' => $canCreateTickets
                ? $workspace->contacts()->orderBy('name')->get(['id', 'name', 'email'])
                : [],
            'teams' => $canCreateTickets
                ? $workspace->teams()->orderBy('name')->get(['id', 'name'])
                : [],
            'canCreateTickets' => $canCreateTickets,
        ]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('create', [Ticket::class, $workspace]);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'channel' => ['required', Rule::enum(TicketChannel::class)],
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('workspace_id', $workspace->id)],
            'assignee_id' => ['nullable', 'integer'],
            'contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->where('workspace_id', $workspace->id)],
            'contact_name' => ['required_without:contact_id', 'nullable', 'string', 'max:100'],
            'contact_email' => ['required_without:contact_id', 'nullable', 'email', 'max:150'],
        ]);

        $ticket = $this->tickets->create($workspace, $request->user(), $validated);

        return redirect()
            ->route('desk.tickets.show', [$workspace, $ticket])
            ->with('success', "Ticket #{$ticket->number} created.");
    }

    public function show(Workspace $workspace, Ticket $ticket): Response
    {
        abort_unless($ticket->workspace_id === $workspace->id, 404);
        Gate::authorize('view', $ticket);

        $canViewInternalNotes = Gate::allows('viewInternalNotes', $ticket);
        $staffRoles = [
            MemberRole::Owner->value,
            MemberRole::Admin->value,
            MemberRole::Manager->value,
            MemberRole::Agent->value,
        ];

        $ticket->load([
            'contact',
            'assignee:id,name,email',
            'slaPolicy',
            'tags:id,name,color',
            'team:id,name',
            'messages' => fn ($q) => $q
                ->when(! $canViewInternalNotes, fn ($messages) => $messages->where('kind', 'reply'))
                ->with(['author:id,name', 'attachments:id,uuid,ticket_message_id,original_name,mime_type,size'])
                ->orderBy('created_at')
                ->orderBy('id'),
        ]);

        return Inertia::render('desk/tickets/show', [
            'ticket' => $ticket,
            'attachmentLimits' => AttachmentLimits::forClient(),
            'agents' => Gate::allows('assign', $ticket)
                ? $workspace->members()->wherePivotIn('role', $staffRoles)->orderBy('name')->get(['users.id', 'users.name'])
                : [],
            'workspaceTags' => $workspace->tags()->orderBy('name')->get(['id', 'name', 'color']),
            'teams' => Gate::allows('assign', $ticket)
                ? $workspace->teams()->orderBy('name')->get(['id', 'name'])
                : [],
            'contactTicketCount' => $ticket->contact->tickets()
                ->visibleTo(request()->user(), $workspace)
                ->count(),
        ]);
    }

    public function update(Request $request, Workspace $workspace, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->workspace_id === $workspace->id, 404);
        Gate::authorize('update', $ticket);

        $validated = $request->validate([
            'subject' => ['sometimes', 'string', 'max:200'],
            'status' => ['sometimes', Rule::enum(TicketStatus::class)],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
            'category' => ['sometimes', Rule::enum(TicketCategory::class)],
            'team_id' => ['sometimes', 'nullable', Rule::exists('teams', 'id')->where('workspace_id', $workspace->id)],
            'assignee_id' => ['sometimes', 'nullable', 'integer'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('tags', 'id')->where('workspace_id', $workspace->id)],
        ]);

        $this->tickets->update($workspace, $request->user(), $ticket, $validated);

        return back()->with('success', 'Ticket updated.');
    }

    public function destroy(Workspace $workspace, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->workspace_id === $workspace->id, 404);
        Gate::authorize('delete', $ticket);

        $ticket->delete();

        return redirect()
            ->route('desk.tickets.index', $workspace)
            ->with('success', "Ticket #{$ticket->number} deleted.");
    }
}
