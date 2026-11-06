<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AnalyticsService;
use App\Services\TicketAttachmentService;
use App\Services\TicketService;
use App\Support\AttachmentLimits;
use App\Support\AttachmentRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class CustomerTicketController extends Controller
{
    public function __construct(
        private TicketAttachmentService $attachments,
        private TicketService $tickets,
        private AnalyticsService $analytics,
    ) {}

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

        $workspaces = $this->customerWorkspaces($user)->get();

        return Inertia::render('customer/tickets/index', [
            'ticketGroups' => $ticketGroups,
            'workspaces' => $workspaces->map(fn (Workspace $workspace) => [
                'id' => $workspace->id,
                'name' => $workspace->name,
            ])->values(),
            'analytics' => $workspaces->map(function (Workspace $workspace) use ($user) {
                $kpis = $this->analytics->forWorkspace($workspace, $user)['kpis'];

                return [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'total' => $kpis['totalTickets'],
                    'open' => $kpis['open'],
                    'resolved' => $kpis['resolvedTickets'],
                    'satisfaction' => $kpis['customerSatisfaction'],
                ];
            })->values(),
        ]);
    }

    /**
     * Create a ticket for the authenticated customer's workspace.
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'workspace_id' => ['required', 'integer'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $workspace = $this->customerWorkspaces($user)->findOrFail($validated['workspace_id']);
        $contact = $workspace->contacts()
            ->where(function (Builder $query) use ($user) {
                $query->where('user_id', $user->getKey())
                    ->orWhereRaw('LOWER(email) = ?', [strtolower($user->email)]);
            })
            ->firstOrFail();

        $ticket = $this->tickets->create($workspace, $user, [
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'priority' => 'normal',
            'channel' => 'web',
            'category' => 'general',
            'contact_id' => $contact->id,
        ]);

        return redirect()
            ->route('customer.tickets.show', $ticket)
            ->with('success', "Ticket #{$ticket->number} created.");
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
                ->with('attachments:id,uuid,ticket_message_id,original_name,mime_type,size')
                ->select(['id', 'ticket_id', 'is_from_contact', 'body', 'created_at']),
        ]);

        return Inertia::render('customer/tickets/show', [
            'attachmentLimits' => AttachmentLimits::forClient(),
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
                    'attachments' => $message->attachments->map(fn (TicketAttachment $file) => [
                        'id' => $file->uuid,
                        'name' => $file->original_name,
                        'size' => $file->size,
                        'mime_type' => $file->mime_type,
                        'url' => route('customer.tickets.attachments.show', [$ticket->id, $file->uuid]),
                    ])->values(),
                ])->values(),
            ],
        ]);
    }

    /**
     * Add a public customer reply without accepting staff-only message fields.
     */
    public function storeReply(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('reply', $ticket);

        $validator = Validator::make($request->all(), [
            'body' => ['required', 'string', 'max:10000'],
            ...AttachmentRules::rules(),
        ], AttachmentRules::messages());
        $validator->after(fn ($v) => AttachmentRules::checkTotalSize($v, $request->file('attachments')));
        $validated = $validator->validate();

        DB::transaction(function () use ($ticket, $request, $validated) {
            $message = $ticket->messages()->create([
                'author_id' => $request->user()->getKey(),
                'kind' => 'reply',
                'is_from_contact' => true,
                'body' => $validated['body'],
            ]);

            $this->attachments->store($message, $validated['attachments'] ?? [], $request->user());
        });

        return back()->with('success', 'Reply sent.');
    }

    /**
     * Record the customer's 1–5 rating once the ticket is resolved or closed.
     */
    public function rate(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('view', $ticket);

        $ticket->loadMissing('contact:id,user_id');
        abort_unless($ticket->contact?->user_id === $request->user()->getKey(), 403);
        abort_unless(in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::Closed], true), 422);

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
        ]);

        $ticket->forceFill([
            'satisfaction_rating' => $validated['rating'],
            'satisfaction_rated_at' => now(),
        ])->save();

        return back()->with('success', 'Thanks for the rating.');
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
            'satisfaction_rating' => $ticket->satisfaction_rating,
            'updated_at' => $ticket->updated_at?->toIso8601String(),
        ];
    }

    private function customerWorkspaces(User $user): Builder
    {
        return Workspace::query()
            ->whereHas('contacts', function (Builder $query) use ($user) {
                $query->where('user_id', $user->getKey())
                    ->orWhereRaw('LOWER(email) = ?', [strtolower($user->email)]);
            })
            ->orderBy('name');
    }
}
