<?php

namespace App\Http\Controllers;

use App\Enums\MemberRole;
use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AutomationEngine;
use App\Services\SlaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function __construct(
        private SlaService $slaService,
        private AutomationEngine $automationEngine,
    ) {
    }

    public function index(Request $request, Workspace $workspace): Response
    {
        Gate::authorize('viewAny', [Ticket::class, $workspace]);

        $filters = [
            'status' => $request->query('status', 'all'),
            'priority' => $request->query('priority'),
            'assignee' => $request->query('assignee'),
            'tag' => $request->query('tag'),
            'q' => $request->query('q'),
        ];

        $visibleTickets = Ticket::query()->visibleTo($request->user(), $workspace);

        $tickets = (clone $visibleTickets)
            ->with(['contact:id,name,email,company', 'assignee:id,name', 'tags:id,name,color'])
            ->when($filters['status'] !== 'all', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['priority'], fn ($q, $priority) => $q->where('priority', $priority))
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
                    $query->where('subject', 'like', "%{$search}%")
                        ->orWhere('number', ltrim($search, '#'))
                        ->orWhereHas('contact', fn ($c) => $c
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
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
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('workspace_id', $workspace->id)],
            'assignee_id' => ['nullable', 'integer'],
            'contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->where('workspace_id', $workspace->id)],
            'contact_name' => ['required_without:contact_id', 'nullable', 'string', 'max:100'],
            'contact_email' => ['required_without:contact_id', 'nullable', 'email', 'max:150'],
        ]);

        $team = ! empty($validated['team_id'])
            ? $workspace->teams()->findOrFail($validated['team_id'])
            : $workspace->teams()->orderBy('id')->first();

        abort_unless($team !== null, 422, 'Create a team before creating tickets.');

        if ($workspace->roleOf($request->user()) === MemberRole::Manager) {
            abort_unless($team->hasMember($request->user()), 403, 'Managers can create tickets only for their teams.');
        }

        if (! empty($validated['assignee_id'])) {
            abort_unless(
                $workspace->members()
                    ->where('user_id', $validated['assignee_id'])
                    ->wherePivotIn('role', [
                        MemberRole::Owner->value,
                        MemberRole::Admin->value,
                        MemberRole::Manager->value,
                        MemberRole::Agent->value,
                    ])
                    ->exists(),
                422,
                'Assignee must be a support team member.',
            );
        }

        $ticket = DB::transaction(function () use ($validated, $workspace, $request, $team) {
            $contact = isset($validated['contact_id'])
                ? $workspace->contacts()->findOrFail($validated['contact_id'])
                : $workspace->contacts()->firstOrCreate(
                    ['email' => $validated['contact_email']],
                    ['name' => $validated['contact_name']],
                );

            $this->linkCustomerAccount($workspace, $contact);

            $ticket = $workspace->tickets()->create([
                'number' => $workspace->nextTicketNumber(),
                'subject' => $validated['subject'],
                'priority' => $validated['priority'],
                'channel' => $validated['channel'],
                'contact_id' => $contact->id,
                'team_id' => $team->id,
                'assignee_id' => $validated['assignee_id'] ?? null,
            ]);

            $ticket->messages()->create([
                'kind' => 'reply',
                'is_from_contact' => true,
                'body' => $validated['body'],
            ]);

            $ticket->logEvent("Ticket created by {$request->user()->name}", $request->user()->id);

            return $ticket;
        });

        $this->slaService->apply($ticket);
        $this->automationEngine->run($ticket, 'ticket_created');

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
                ->with('author:id,name')
                ->orderBy('created_at'),
        ]);

        return Inertia::render('desk/tickets/show', [
            'ticket' => $ticket,
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
            'permissions' => [
                'update' => Gate::allows('update', $ticket),
                'assign' => Gate::allows('assign', $ticket),
                'delete' => Gate::allows('delete', $ticket),
                'addInternalNote' => Gate::allows('addInternalNote', $ticket),
            ],
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
            'team_id' => ['sometimes', 'nullable', Rule::exists('teams', 'id')->where('workspace_id', $workspace->id)],
            'assignee_id' => ['sometimes', 'nullable', 'integer'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('tags', 'id')->where('workspace_id', $workspace->id)],
        ]);

        $user = $request->user();

        if (array_key_exists('assignee_id', $validated) || array_key_exists('team_id', $validated)) {
            Gate::authorize('assign', $ticket);
        }

        if (array_key_exists('team_id', $validated) && $validated['team_id'] !== $ticket->team_id) {
            $team = $validated['team_id'] === null
                ? null
                : $workspace->teams()->findOrFail($validated['team_id']);

            if ($workspace->roleOf($user) === MemberRole::Manager) {
                abort_unless($team?->hasMember($user), 403, 'Managers can move tickets only to their teams.');
            }

            $ticket->team_id = $team?->id;
            $ticket->logEvent('Team changed to '.($team?->name ?? 'Unassigned').' by '.$user->name, $user->id);
        }

        if (array_key_exists('status', $validated) && $validated['status'] !== $ticket->status->value) {
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

            $ticket->logEvent("Status changed to {$newStatus->value} by {$user->name}", $user->id);
        }

        $priorityChanged = false;
        if (array_key_exists('priority', $validated) && $validated['priority'] !== $ticket->priority->value) {
            $ticket->priority = $validated['priority'];
            $priorityChanged = true;
            $ticket->logEvent("Priority changed to {$validated['priority']} by {$user->name}", $user->id);
        }

        if (array_key_exists('assignee_id', $validated) && $validated['assignee_id'] !== $ticket->assignee_id) {
            if ($validated['assignee_id'] !== null) {
                $member = $workspace->members()->where('user_id', $validated['assignee_id'])->first();
                abort_unless(
                    $member !== null && in_array($member->pivot->role, [
                        MemberRole::Owner->value,
                        MemberRole::Admin->value,
                        MemberRole::Manager->value,
                        MemberRole::Agent->value,
                    ], true),
                    422,
                    'Assignee must be a support team member.',
                );
                $ticket->assignee_id = $member->id;
                $ticket->logEvent("Assigned to {$member->name} by {$user->name}", $user->id);
            } else {
                $ticket->assignee_id = null;
                $ticket->logEvent("Unassigned by {$user->name}", $user->id);
            }
        }

        if (array_key_exists('subject', $validated)) {
            $ticket->subject = $validated['subject'];
        }

        $ticket->save();

        if (array_key_exists('tag_ids', $validated)) {
            $ticket->tags()->sync($validated['tag_ids']);
        }

        if ($priorityChanged) {
            $this->slaService->apply($ticket);
        }

        $this->automationEngine->run($ticket, 'ticket_updated');

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

    private function linkCustomerAccount(Workspace $workspace, Contact $contact): void
    {
        if ($contact->user_id) {
            return;
        }

        $userId = User::query()
            ->where('email', Str::lower($contact->email))
            ->value('id');

        if (! $userId) {
            return;
        }

        $contact->update(['user_id' => $userId]);

        if (! $workspace->hasMember($userId)) {
            $workspace->members()->attach($userId, ['role' => MemberRole::Customer->value]);
        }
    }
}
