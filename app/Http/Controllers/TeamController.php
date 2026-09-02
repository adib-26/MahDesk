<?php

namespace App\Http\Controllers;

use App\Enums\MemberRole;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        Gate::authorize('manageTeams', $workspace);

        return Inertia::render('desk/settings/teams', [
            'teams' => $workspace->teams()
                ->with([
                    'members' => fn ($query) => $query
                        ->select(['users.id', 'users.name', 'users.email'])
                        ->orderBy('users.name'),
                ])
                ->withCount('tickets')
                ->orderBy('name')
                ->get(),
            'members' => $workspace->members()
                ->wherePivotIn('role', MemberRole::staffValues())
                ->orderBy('users.name')
                ->get(['users.id', 'users.name', 'users.email'])
                ->map(fn (User $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->pivot->role,
                ]),
        ]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('manageTeams', $workspace);

        $workspace->teams()->create($this->validatedTeam($request, $workspace));

        return back()->with('success', 'Team created.');
    }

    public function update(Request $request, Workspace $workspace, Team $team): RedirectResponse
    {
        Gate::authorize('manageTeams', $workspace);
        $this->ensureTeamBelongsToWorkspace($workspace, $team);

        $team->update($this->validatedTeam($request, $workspace, $team));

        return back()->with('success', 'Team renamed.');
    }

    public function destroy(Workspace $workspace, Team $team): RedirectResponse
    {
        Gate::authorize('manageTeams', $workspace);
        $this->ensureTeamBelongsToWorkspace($workspace, $team);

        $team->delete();

        return back()->with('success', 'Team deleted. Tickets remain and are no longer assigned to a team.');
    }

    public function storeMember(Request $request, Workspace $workspace, Team $team): RedirectResponse
    {
        Gate::authorize('manageTeams', $workspace);
        $this->ensureTeamBelongsToWorkspace($workspace, $team);

        $validated = $request->validate([
            'member_id' => [
                'required',
                'integer',
                Rule::exists('workspace_members', 'user_id')->where('workspace_id', $workspace->id),
            ],
        ]);

        /** @var User $member */
        $member = $workspace->members()->whereKey($validated['member_id'])->firstOrFail();

        if ($team->hasMember($member)) {
            return back()->withErrors(['member_id' => 'That workspace member is already on this team.']);
        }

        $team->members()->attach($member->id);

        return back()->with('success', "{$member->name} added to {$team->name}.");
    }

    public function destroyMember(Workspace $workspace, Team $team, User $member): RedirectResponse
    {
        Gate::authorize('manageTeams', $workspace);
        $this->ensureTeamBelongsToWorkspace($workspace, $team);
        abort_unless($workspace->hasMember($member), 404);
        abort_unless($team->hasMember($member), 404);

        $team->members()->detach($member->id);

        return back()->with('success', "{$member->name} removed from {$team->name}.");
    }

    /**
     * @return array{name: string}
     */
    private function validatedTeam(Request $request, Workspace $workspace, ?Team $team = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('teams', 'name')
                    ->where('workspace_id', $workspace->id)
                    ->ignore($team?->id),
            ],
        ]);
    }

    private function ensureTeamBelongsToWorkspace(Workspace $workspace, Team $team): void
    {
        abort_unless($team->workspace_id === $workspace->id, 404);
    }
}
