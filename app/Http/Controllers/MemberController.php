<?php

namespace App\Http\Controllers;

use App\Enums\MemberRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function index(Workspace $workspace): Response
    {
        Gate::authorize('manageMembers', $workspace);

        $openCounts = $workspace->tickets()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNotNull('assignee_id')
            ->selectRaw('assignee_id, count(*) as total')
            ->groupBy('assignee_id')
            ->pluck('total', 'assignee_id');

        $members = $workspace->members()
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.email'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->pivot->role,
                'can_view_unassigned' => (bool) $u->pivot->can_view_unassigned,
                'joined_at' => $u->pivot->created_at?->toDateString(),
                'open_tickets' => $openCounts->get($u->id, 0),
            ]);

        $invitations = $workspace->invitations()
            ->pending()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'role', 'can_view_unassigned', 'expires_at']);

        return Inertia::render('desk/settings/members', [
            'members' => $members,
            'invitations' => $invitations,
        ]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('manageMembers', $workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'role' => ['required', Rule::in(MemberRole::inviteableValues())],
            'can_view_unassigned' => ['sometimes', 'boolean'],
        ]);

        $email = strtolower($validated['email']);

        if ($workspace->members()->whereRaw('lower(users.email) = ?', [$email])->exists()) {
            return back()->withErrors(['email' => 'That person is already a member of this workspace.']);
        }

        $invitation = WorkspaceInvitation::issue(
            $workspace,
            $email,
            MemberRole::from($validated['role']),
            $request->user(),
            $validated['name'],
            (bool) ($validated['can_view_unassigned'] ?? false),
        );

        Notification::route('mail', $invitation->email)
            ->notify(new WorkspaceInvitationNotification($invitation));

        $this->audit->record(
            'invitation.sent',
            $request->user(),
            $workspace,
            $invitation,
            ['email' => $invitation->email, 'role' => $invitation->role],
        );

        return back()->with('success', "Invitation sent to {$invitation->email}.");
    }

    public function update(Request $request, Workspace $workspace, User $member): RedirectResponse
    {
        Gate::authorize('manageMembers', $workspace);

        $validated = $request->validate([
            'role' => ['required', Rule::in(MemberRole::inviteableValues())],
            'can_view_unassigned' => ['sometimes', 'boolean'],
        ]);

        if ($workspace->roleOf($member) === MemberRole::Owner) {
            return back()->withErrors(['role' => 'The workspace owner role cannot be changed.']);
        }

        if ($member->id === $request->user()->id) {
            return back()->withErrors(['role' => 'You cannot change your own role.']);
        }

        $canViewUnassigned = MemberRole::from($validated['role']) === MemberRole::Agent
            ? (bool) ($validated['can_view_unassigned'] ?? false)
            : false;

        $workspace->members()->updateExistingPivot($member->id, [
            'role' => $validated['role'],
            'can_view_unassigned' => $canViewUnassigned,
        ]);

        if (in_array($validated['role'], [MemberRole::Manager->value, MemberRole::Agent->value], true)) {
            $defaultTeam = $workspace->teams()->firstOrCreate(['name' => 'General Support']);
            $defaultTeam->members()->syncWithoutDetaching([$member->id]);
        }

        $this->audit->record(
            'member.updated',
            $request->user(),
            $workspace,
            $member,
            ['role' => $validated['role'], 'can_view_unassigned' => $canViewUnassigned],
        );

        return back()->with('success', "{$member->name} is now ".MemberRole::from($validated['role'])->label().'.');
    }

    public function destroy(Request $request, Workspace $workspace, User $member): RedirectResponse
    {
        Gate::authorize('manageMembers', $workspace);

        if ($workspace->roleOf($member) === MemberRole::Owner) {
            return back()->withErrors(['role' => 'The workspace owner cannot be removed.']);
        }

        if ($member->id === $request->user()->id) {
            return back()->withErrors(['role' => 'You cannot remove yourself.']);
        }

        $workspace->members()->detach($member->id);
        $workspace->tickets()->where('assignee_id', $member->id)->update(['assignee_id' => null]);

        $this->audit->record(
            'member.removed',
            $request->user(),
            $workspace,
            $member,
            ['email' => $member->email],
        );

        return back()->with('success', "{$member->name} removed from the workspace.");
    }
}
