<?php

namespace App\Http\Controllers;

use App\Enums\MemberRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\AuditLogger;
use App\Services\HomeRedirector;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function __construct(
        private AuditLogger $audit,
        private HomeRedirector $home,
    ) {
    }

    public function show(string $token): Response|RedirectResponse
    {
        $invitation = $this->pendingInvitation($token);

        if (! $invitation) {
            return Inertia::render('invitations/expired');
        }

        if (! Auth::check()) {
            request()->session()->put('url.intended', route('invitations.show', $token));
        }

        $user = Auth::user();

        return Inertia::render('invitations/show', [
            'invitation' => [
                'token' => $invitation->token,
                'email' => $invitation->email,
                'name' => $invitation->name,
                'role' => $invitation->role()->label(),
                'workspace' => $invitation->workspace->only(['name']),
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
            'authenticatedEmail' => $user?->email,
            'emailMatches' => $user !== null && strcasecmp($user->email, $invitation->email) === 0,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->pendingInvitation($token);

        if (! $invitation) {
            return to_route('login')->with('error', 'This invitation is invalid or has expired.');
        }

        $user = $request->user();

        if ($user === null) {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
            ]);

            if (User::query()->whereRaw('lower(email) = ?', [$invitation->email])->exists()) {
                return to_route('login')
                    ->with('status', 'An account already exists for this email. Sign in to accept the invitation.');
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $invitation->email,
                'password' => Hash::make($validated['password']),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            event(new Registered($user));
            Auth::login($user);
            $request->session()->regenerate();
        } elseif (strcasecmp($user->email, $invitation->email) !== 0) {
            throw ValidationException::withMessages([
                'email' => ['Sign in with '.$invitation->email.' to accept this invitation.'],
            ]);
        }

        if ($invitation->workspace->hasMember($user)) {
            $invitation->forceFill(['accepted_at' => now()])->save();

            return redirect()
                ->to($this->home->url($user))
                ->with('error', 'You are already a member of this workspace.');
        }

        $invitation->workspace->members()->attach($user->id, [
            'role' => $invitation->role,
            'can_view_unassigned' => $invitation->can_view_unassigned,
        ]);

        if (in_array($invitation->role, [MemberRole::Manager->value, MemberRole::Agent->value, MemberRole::Owner->value], true)) {
            $defaultTeam = $invitation->workspace->teams()->firstOrCreate(['name' => 'General Support']);
            $defaultTeam->members()->syncWithoutDetaching([$user->id]);
        }

        $invitation->forceFill(['accepted_at' => now()])->save();

        $this->audit->record(
            'invitation.accepted',
            $user,
            $invitation->workspace,
            $invitation,
            ['role' => $invitation->role],
        );

        return redirect()
            ->to($this->home->url($user->fresh()))
            ->with('success', "You have joined {$invitation->workspace->name}.");
    }

    public function destroy(Workspace $workspace, WorkspaceInvitation $invitation): RedirectResponse
    {
        abort_unless($invitation->workspace_id === $workspace->id, 404);
        Gate::authorize('manageMembers', $workspace);

        $email = $invitation->email;
        $invitation->delete();

        $this->audit->record(
            'invitation.revoked',
            request()->user(),
            $workspace,
            $invitation,
            ['email' => $email],
        );

        return back()->with('success', 'Invitation revoked.');
    }

    private function pendingInvitation(string $token): ?WorkspaceInvitation
    {
        $invitation = WorkspaceInvitation::query()
            ->with('workspace')
            ->where('token', $token)
            ->first();

        return $invitation?->isPending() ? $invitation : null;
    }
}
