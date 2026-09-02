<?php

namespace App\Http\Controllers;

use App\Enums\MemberRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
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
                'joined_at' => $u->pivot->created_at?->toDateString(),
                'open_tickets' => $openCounts->get($u->id, 0),
            ]);

        return Inertia::render('desk/settings/members', [
            'members' => $members,
        ]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('manageMembers', $workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'role' => ['required', Rule::in([
                MemberRole::Admin->value,
                MemberRole::Manager->value,
                MemberRole::Agent->value,
            ])],
        ]);

        $user = User::firstOrCreate(
            ['email' => $validated['email']],
            ['name' => $validated['name'], 'password' => Str::random(40)],
        );

        if ($workspace->members()->where('user_id', $user->id)->exists()) {
            return back()->withErrors(['email' => 'That person is already a member of this workspace.']);
        }

        $workspace->members()->attach($user->id, ['role' => $validated['role']]);

        if (in_array($validated['role'], [MemberRole::Manager->value, MemberRole::Agent->value], true)) {
            $defaultTeam = $workspace->teams()->firstOrCreate(['name' => 'General Support']);
            $defaultTeam->members()->syncWithoutDetaching([$user->id]);
        }

        if ($user->wasRecentlyCreated) {
            event(new Registered($user));
        }

        $note = $user->wasRecentlyCreated
            ? ' An account was created; they can set a password via "Forgot password".'
            : '';

        $roleLabel = MemberRole::from($validated['role'])->label();

        return back()->with('success', "{$user->name} added as {$roleLabel}.{$note}");
    }

    public function update(Request $request, Workspace $workspace, User $member): RedirectResponse
    {
        Gate::authorize('manageMembers', $workspace);

        $validated = $request->validate([
            'role' => ['required', Rule::in([
                MemberRole::Admin->value,
                MemberRole::Manager->value,
                MemberRole::Agent->value,
            ])],
        ]);

        if ($workspace->roleOf($member) === MemberRole::Owner) {
            return back()->withErrors(['role' => 'The workspace owner role cannot be changed.']);
        }

        if ($member->id === $request->user()->id) {
            return back()->withErrors(['role' => 'You cannot change your own role.']);
        }

        $workspace->members()->updateExistingPivot($member->id, ['role' => $validated['role']]);

        if (in_array($validated['role'], [MemberRole::Manager->value, MemberRole::Agent->value], true)) {
            $defaultTeam = $workspace->teams()->firstOrCreate(['name' => 'General Support']);
            $defaultTeam->members()->syncWithoutDetaching([$member->id]);
        }

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

        return back()->with('success', "{$member->name} removed from the workspace.");
    }
}
